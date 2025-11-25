<?php

namespace App\Http\Controllers\BlotterReports;

use App\Http\Controllers\Controller;
use App\Interfaces\BlotterReportRepositoryInterface;
use App\Models\BlotterReport\BlotterReport;
use App\Models\Users\User;
use App\Notifications\BlotterReportStatusUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BlotterReportsController extends Controller
{

    public function __construct(private readonly BlotterReportRepositoryInterface $blotter_report)
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function findAll(Request $request)
    {
        $perPage = $request->integer('per_page');
        $search = $request->string('search');
        $statuses = $this->resolveStatuses($request);
        $user = $request->user()?->loadMissing('profile');

        if ($user && $user->role === 'staff') {
            $handlerProfileId = $user->profile?->id;
            $staffStatuses = $this->resolveStaffStatuses($statuses);
            $certificates = $this->blotter_report->getAllHandledByStaff(['profile', 'handler.user'], $handlerProfileId, $perPage, $staffStatuses, $search);
        } else {
            $certificates = $this->blotter_report->getAll(['profile', 'handler.user'], $perPage, $statuses, $search);
        }

        return response()->json([
            'status' => 'success',
            'data' => $certificates
        ]);
    }

    /**
     * Display a listing of the resource by Id.
     */
    public function findAllById(Request $request)
    {
        $perPage = $request->integer('per_page');
        $userId = $request->integer('user_id');
        $search = $request->string('search');
        $statuses = $this->resolveStatuses($request);
        $blotter_report = $this->blotter_report->getAllById(['profile', 'handler.user'], $userId, $perPage, $statuses, $search);

        return response()->json([
            'status' => 'success',
            'data' => $blotter_report
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, int $id)
    {
        $user = User::with('profile')->findOrFail($id);
        $profileId = $user->profile?->id;

        if (!$profileId) {
            throw ValidationException::withMessages([
                'user' => 'Unable to file a report without a complete resident profile.',
            ]);
        }

        $validated = $this->validateStorePayload($request);

        $incidentDateTime = $this->mergeIncidentDateTime(
            $validated['date_of_incident'],
            $validated['time_of_incident']
        );

        $blotterPayload = [
            'incident_type' => $validated['incident_type'],
            'incident_title' => $validated['incident_title'] ?? null,
            'datetime_of_incident' => $incidentDateTime,
            'location' => $validated['incident_street_address'] ?? $validated['incident_address_line'],
            'landmark' => $validated['incident_address_line'] ?? null,
            'person_involved' => $this->sanitizeList($validated['incident_people_involved'] ?? []),
            'witnesses' => $this->sanitizeList($validated['incident_witnesses'] ?? []),
            'description' => $validated['incident_description'],
            'status' => BlotterReport::STATUS_PENDING,
        ];

        $blotterPayload = array_filter(
            $blotterPayload,
            static fn($value) => !is_null($value) && $value !== ''
        );

        $blotterReport = $this->blotter_report->createBlotterReport($blotterPayload, $profileId);

        $this->storeEvidences($request->file('evidences', []), $blotterReport);

        return response()->json([
            'status' => 'success',
            'data' => $blotterReport->load(['profile', 'handler', 'evidence'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $blotterReportId)
    {
        $blotter_report = $this->blotter_report->getById($blotterReportId, ['profile', 'handler.user', 'evidence']);

        return response()->json([
            'status' => 'success',
            'data' => $blotter_report
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $blotterReport = $this->blotter_report->getById($id, []);

        $user = $request->user()->loadMissing('profile');
        $handlerProfileId = $user->profile?->id;

        if ($blotterReport->profile) {
            $blotterReport->profile->notify(
                new BlotterReportStatusUpdated($blotterReport, $user)
            );
        }

        if (is_null($blotterReport)) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:255']
        ]);

        $certificateData = collect($validated)
            ->only(['status'])
            ->filter(fn($value) => !is_null($value))
            ->toArray();
        $certificateData['handled_by'] = $handlerProfileId;

        $certificate = $this->blotter_report->updateBlotterReport($blotterReport, $certificateData);

        return response()->json([
            'status' => 'success',
            'data' => $certificate
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id)
    {

    }


    private function resolveStatuses(Request $request): ?array
    {
        $statuses = $request->input('statuses');

        if (is_null($statuses)) {
            return null;
        }

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        if (!is_array($statuses)) {
            return null;
        }

        $allowedStatuses = [
            BlotterReport::STATUS_PENDING,
            BlotterReport::STATUS_APPROVED,
            BlotterReport::STATUS_REJECTED,
            BlotterReport::STATUS_CANCELLED,
            BlotterReport::STATUS_RELEASED,
            BlotterReport::STATUS_DONE,
        ];

        $filteredStatuses = array_values(array_unique(array_filter(array_map(
            static fn($status) => is_string($status) ? strtolower(trim($status)) : null,
            $statuses
        ))));

        $validStatuses = array_values(array_intersect($filteredStatuses, $allowedStatuses));

        return !empty($validStatuses) ? $validStatuses : null;
    }

    private function resolveStaffStatuses(?array $statuses): array
    {
        $allowedStatuses = [
            BlotterReport::STATUS_PENDING,
            BlotterReport::STATUS_APPROVED,
        ];

        if (is_null($statuses)) {
            return $allowedStatuses;
        }

        $filtered = array_values(array_intersect($statuses, $allowedStatuses));

        return !empty($filtered) ? $filtered : $allowedStatuses;
    }

    private function validateStorePayload(Request $request): array
    {
        return $request->validate([
            'incident_type' => ['required', 'string', 'max:255'],
            'incident_title' => ['nullable', 'string', 'max:255'],
            'date_of_incident' => ['required', 'date'],
            'time_of_incident' => ['required', 'date_format:H:i'],
            'incident_street_address' => ['required_without:incident_address_line', 'string', 'max:255'],
            'incident_address_line' => ['nullable', 'string', 'max:255'],
            'incident_people_involved' => ['nullable', 'array', 'max:10'],
            'incident_people_involved.*' => ['nullable', 'string', 'max:255'],
            'incident_witnesses' => ['nullable', 'array', 'max:10'],
            'incident_witnesses.*' => ['nullable', 'string', 'max:255'],
            'incident_description' => ['required', 'string'],
            'evidences' => ['nullable', 'array', 'max:10'],
            'evidences.*' => ['file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/jpg,application/pdf,video/mp4'],
        ]);
    }

    private function mergeIncidentDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse("{$date} {$time}");
    }

    private function sanitizeList(?array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        $filtered = array_values(array_filter(array_map(
            static fn($value) => is_string($value) ? trim($value) : null,
            $values
        )));

        return !empty($filtered) ? $filtered : null;
    }

    /**
     * @param UploadedFile[] $files
     */
    private function storeEvidences(array $files, BlotterReport $blotterReport): void
    {
        if (empty($files)) {
            return;
        }

        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = "blotter-evidences/{$blotterReport->id}/{$filename}";

            // Upload to Supabase S3
            Storage::disk('supabase')->put(
                $path,
                file_get_contents($file),
                [
                    'ContentType' => $file->getClientMimeType(),
                ]
            );

            $blotterReport->evidence()->create([
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $blotterReport->load('evidence');
    }
}
