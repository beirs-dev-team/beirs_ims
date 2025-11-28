<script setup lang="ts">
import WarningLabel from '@/components/common/WarningLabel/WarningLabel.vue';
import { computed, ref, watch } from 'vue';
import { useBlotterReports } from './composable/useBlotterReport';
import FormFloatingInput from '@/components/common/FormFloatingInput/FormFloatingInput.vue';
import DropdownInput from '@/components/common/DropdownInput/DropdownInput.vue';
import FormButton from '@/components/common/FormButton/FormButton.vue';
import FormTextAreaInput from '@/components/common/FormTextAreaInput/FormTextAreaInput.vue';
import { useGlobalLoadingStore } from '@/Utils/store/useGlobalLoadingStore';
import type { ApiErrorResponse, BlotterReportRequestPayload, CommonResponse, UserProfile as UserProfileType } from '@/Types';
import UploadFiles from './components/UploadFiles.vue';
import { maxDate, orderedOptions } from '@/Utils/helpers/formatters';
import { submitBlotterReport } from '@/Utils/blotterReportServices';
import type { AxiosError } from 'axios';
import router from '@/router';
import { useRoute } from 'vue-router';
import { fetchSingleUserProfile } from '@/Utils/userServices';
import { useBarangayAddresses } from '@/composables/useBarangayAddresses';

const props = defineProps<{
  role: string,
  id: string
}>()

const {
  form,
  errors,
  errorMessages,
  incidentTypeOptions,
  validateForm,
  setServerErrors
} = useBlotterReports();

const navigation = useGlobalLoadingStore();
const route = useRoute();
const { addressOptions, isLoadingAddresses, loadBarangayAddresses } = useBarangayAddresses();
loadBarangayAddresses();

const hasError = ref<boolean>(false);
const complainantFullName = ref<string>('')
const complainantAge = ref<string>('')
const complainantContactInfo = ref<string>('')
const address = ref<string>('')

const incidentTypeOptionLabels = incidentTypeOptions.map(incidentType => incidentType.label)

const sortedIncidentTypeOptions = orderedOptions(incidentTypeOptionLabels)

const buildFullName = (profile: Partial<UserProfileType>): string => {
  const first = profile.first_name ?? ''
  const middle = profile.middle_name ?? ''
  const last = profile.last_name ?? ''
  return [first, middle, last].filter(Boolean).join(' ').trim()
}

const computeAge = (birthDate?: string | null): string => {
  if (!birthDate) return ''
  const birth = new Date(birthDate)
  if (Number.isNaN(birth.valueOf())) return ''

  const today = new Date()
  let age = today.getFullYear() - birth.getFullYear()
  const monthDiff = today.getMonth() - birth.getMonth()
  const dayDiff = today.getDate() - birth.getDate()
  if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
    age--
  }
  return age >= 0 ? `${age}` : ''
}

const fetchUserProfile = async () => {
  if (!props.id) return

  navigation.startNavigation();
  try {
    hasError.value = false
    const response = await fetchSingleUserProfile(props.id)
    const profile = (response.data?.profile ?? {}) as Partial<UserProfileType>

    complainantFullName.value = buildFullName(profile)
    complainantContactInfo.value = profile.mobile_number ?? ''
    const street = profile.street_address ?? ''
    const addressLine = profile.address_line ?? ''
    address.value = [street, addressLine].filter(Boolean).join(', ')
    complainantAge.value = computeAge(profile.date_of_birth)
  } catch (error) {
    const axiosError = error as AxiosError<ApiErrorResponse>;
    const fallbackResponse = error as CommonResponse;
    const responseData = axiosError?.response?.data
    setServerErrors(responseData?.errors, responseData?.message ?? fallbackResponse?.message);
    hasError.value = true
  } finally {
    navigation.endNavigation();
  }
}

watch(() => props.id, (newId, oldId) => {
  if (newId && newId !== oldId) {
    fetchUserProfile()
  }
}, { immediate: true })

const handleCreateBlotterReport = async () => {
  hasError.value = !validateForm();
  if (hasError.value) return;

  if (!props.id) {
    setServerErrors(undefined, 'Missing resident identifier for this request.');
    hasError.value = true;
    return;
  }

  const resolvedRole = props.role || (route.params.role as string | undefined);
  if (!resolvedRole) {
    setServerErrors(undefined, 'Missing role parameter for this request.');
    hasError.value = true;
    return;
  }

  const requestPayload: BlotterReportRequestPayload = {
    incident_type: form.value.incidentType,
    incident_title: form.value.incidentTitle,
    date_of_incident: form.value.dateOfIncident,
    time_of_incident: form.value.timeOfIncident,
    incident_street_address: form.value.incidentStreetAddress,
    incident_address_line: form.value.incidentAddressLine,
    incident_people_involved: form.value.incidentPeopleInvolved,
    incident_witnesses: form.value.incidentWitnesses,
    incident_description: form.value.incidentDescription,
    evidences: form.value.evidences,
  }

  navigation.startNavigation();
  try {
    const response = await submitBlotterReport(requestPayload, props.id);
    if (response.status !== 'success') {
      throw response;
    }
    hasError.value = false;
    await router.push({ name: 'BlotterReports', params: { role: resolvedRole } });

    globalThis.location.reload();
  } catch (error) {
    const axiosError = error as AxiosError<ApiErrorResponse>;
    const fallbackResponse = error as CommonResponse;

    if (axiosError?.isAxiosError) {
      const responseData = axiosError.response?.data;
      setServerErrors(responseData?.errors, responseData?.message);
    } else if (fallbackResponse?.message) {
      setServerErrors(undefined, fallbackResponse.message);
    } else {
      setServerErrors(undefined, 'Failed to submit blotter report.');
    }

    hasError.value = true;
  } finally {
    navigation.endNavigation();
  }
}

const addPersonInvolvedField = () => {
  if (form.value.incidentPeopleInvolved.length > 10) return
  form.value.incidentPeopleInvolved.push('')
}

const addWitnessField = () => {
  if (form.value.incidentWitnesses.length > 10) return
  form.value.incidentWitnesses.push('')
}

const removePersonInvolvedField = (index: number) => {
  form.value.incidentPeopleInvolved.splice(index, 1);
}
const removeWitnessField = (index: number) => {
  form.value.incidentWitnesses.splice(index, 1);
}

const filteredErrors = computed(() => {
  return Object.values(errorMessages.value).filter(message => message.error && message.error.trim() !== '')
})
</script>

<template>
  <section class="create-report-wrapper py-4 py-lg-5">
    <div class="container-xxl">
      <div class="row g-4 align-items-stretch">
        <div class="col-lg-7">
          <div class="hero-card shadow-sm h-100 p-4 p-lg-5">
            <p class="eyebrow text-primary mb-2">Barangay Blotter</p>
            <h2 class="fw-bold text-dark mb-3">Report incidents with clarity and confidence</h2>
            <p class="text-muted mb-4">
              Share the details of what happened so our barangay team can review and act quickly. Keep the story factual
              and include witnesses or proof when available.
            </p>
            <div class="row g-3">
              <div class="col-sm-4">
                <div class="stat-tile">
                  <small class="text-uppercase text-muted fw-semibold">Profile</small>
                  <p class="mb-0 text-dark fw-semibold">{{ complainantFullName || 'Loading…' }}</p>
                  <span class="badge rounded-pill bg-body-secondary mt-2 text-dark fw-semibold">Auto-filled</span>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="stat-tile">
                  <small class="text-uppercase text-muted fw-semibold">Average review</small>
                  <p class="mb-0 text-dark fw-semibold">24-48 hrs</p>
                  <span class="text-muted small">Response time</span>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="stat-tile">
                  <small class="text-uppercase text-muted fw-semibold">Need help?</small>
                  <p class="mb-0 text-dark fw-semibold">Barangay Desk</p>
                  <span class="text-muted small">Weekdays 9AM-4PM</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="tip-card h-100 p-4">
            <h5 class="fw-semibold text-dark mb-3">Quick tips before you submit</h5>
            <ul class="list-unstyled text-muted small mb-4">
              <li class="d-flex align-items-start gap-2 mb-2">
                <i class="bi bi-check-circle-fill text-primary"></i>
                Provide concise titles and timelines.
              </li>
              <li class="d-flex align-items-start gap-2 mb-2">
                <i class="bi bi-check-circle-fill text-primary"></i>
                Add people involved or witnesses for clarity.
              </li>
              <li class="d-flex align-items-start gap-2 mb-2">
                <i class="bi bi-check-circle-fill text-primary"></i>
                Upload photos, screenshots, or PDFs for evidence.
              </li>
              <li class="d-flex align-items-start gap-2 mb-0">
                <i class="bi bi-check-circle-fill text-primary"></i>
                Keep private information secure and respectful.
              </li>
            </ul>
            <div class="d-flex flex-column flex-sm-row gap-2">
              <div class="pill bg-body-secondary text-dark">Upload clear media files</div>
              <div class="pill bg-primary-subtle text-primary fw-semibold">Stay factual & calm</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mt-4 form-card">
        <div class="card-body p-4 p-lg-5">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
            <div>
              <p class="eyebrow text-muted mb-1">Incident Report</p>
              <h4 class="fw-bold mb-0 text-dark">Create Blotter Report</h4>
            </div>
            <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fw-semibold">
              <i class="bi bi-stopwatch me-1"></i>
              Average completion: 5 mins
            </span>
          </div>

          <WarningLabel :has-error="hasError && filteredErrors.length > 0" :errors="filteredErrors" />

          <form class="d-flex flex-column gap-4" @submit.prevent="handleCreateBlotterReport">
            <div class="section-card">
              <div class="section-heading">
                <div>
                  <p class="eyebrow text-muted mb-1">Resident details</p>
                  <h5 class="mb-0 text-dark">Complainant Information</h5>
                </div>
                <span class="badge text-bg-light border fw-semibold d-inline-flex align-items-center gap-1">
                  <i class="bi bi-shield-check"></i>
                  Auto-filled
                </span>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <FormFloatingInput type="text" label="Complainant Name" id="complainant-name"
                    v-model="complainantFullName" :is-disabled="true" />
                </div>
                <div class="col-md-3">
                  <FormFloatingInput type="text" label="Age" id="complainant-age" v-model="complainantAge"
                    :is-disabled="true" />
                </div>
                <div class="col-md-3">
                  <FormFloatingInput type="text" label="Contact Number" id="complainant-contact"
                    v-model="complainantContactInfo" :is-disabled="true" />
                </div>
                <div class="col-12">
                  <FormFloatingInput type="text" label="Registered Address" id="complainant-address" v-model="address"
                    :is-disabled="true" />
                </div>
              </div>
            </div>

            <div class="section-card">
              <div class="section-heading">
                <div>
                  <p class="eyebrow text-muted mb-1">Incident overview</p>
                  <h5 class="mb-0 text-dark">What happened?</h5>
                </div>
                <span class="text-muted small">Required fields</span>
              </div>
              <div class="row g-3">
                <div class="col-lg-6">
                  <DropdownInput :options="sortedIncidentTypeOptions" label="Incident Type" id="incident-type"
                    v-model="form.incidentType" :error-message="errorMessages.incidentType.error"
                    :has-error="errors.incidentType" />
                </div>
                <div class="col-lg-6">
                  <FormFloatingInput type="text" label="Incident Subject/Title" id="incident-title"
                    v-model="form.incidentTitle" :error-message="errorMessages.incidentTitle.error"
                    :has-error="errors.incidentTitle" />
                </div>
                <div class="col-md-6">
                  <FormFloatingInput type="date" label="Date of Incident" id="incident-date" :is-capitalized="false"
                    v-model="form.dateOfIncident" :max="maxDate()" :error-message="errorMessages.dateOfIncident.error"
                    :has-error="errors.dateOfIncident" />
                </div>
                <div class="col-md-6">
                  <FormFloatingInput type="time" label="Time of Incident" id="incident-time"
                    v-model="form.timeOfIncident" :error-message="errorMessages.timeOfIncident.error"
                    :has-error="errors.timeOfIncident" />
                </div>
                <div class="col-md-6">
                  <DropdownInput :options="addressOptions" label="Street / Zone" id="incident-street"
                    v-model="form.incidentStreetAddress" :error-message="errorMessages.incidentStreetAddress.error"
                    :has-error="errors.incidentStreetAddress" :is-disabled="isLoadingAddresses" />
                </div>
                <div class="col-md-6">
                  <FormFloatingInput type="text" label="Landmark / Additional Directions" id="incident-address-line"
                    v-model="form.incidentAddressLine" :error-message="errorMessages.incidentAddressLine.error"
                    :has-error="errors.incidentAddressLine" />
                </div>
              </div>
            </div>

            <div class="section-card">
              <div class="section-heading flex-column flex-lg-row align-items-lg-center">
                <div>
                  <p class="eyebrow text-muted mb-1">Optional details</p>
                  <h5 class="mb-0 text-dark">People involved & witnesses</h5>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
                  <button type="button" class="btn btn-outline-primary btn-sm" @click="addPersonInvolvedField">
                    <i class="bi bi-person-plus me-1"></i>Add Person Involved
                  </button>
                  <button type="button" class="btn btn-outline-primary btn-sm" @click="addWitnessField">
                    <i class="bi bi-people me-1"></i>Add Witness
                  </button>
                </div>
              </div>
              <div class="row g-4">
                <div class="col-lg-6">
                  <p class="text-muted small mb-2">People directly involved</p>
                  <div class="input-list">
                    <div v-for="(person, index) in form.incidentPeopleInvolved" :key="`person-${index}`"
                      class="row g-2 align-items-start mb-2">
                      <div class="col">
                        <FormFloatingInput type="text" label="Person Involved" :id="`people-involved-${index}`"
                          v-model="form.incidentPeopleInvolved[index]" :optional="true"
                          :has-error="errors.incidentPeopleInvolved"
                          :error-message="errorMessages.incidentPeopleInvolved.error" />
                      </div>
                      <div class="col-auto" v-if="form.incidentPeopleInvolved.length > 1">
                        <button type="button" class="btn btn-link text-danger p-2 remove-btn" aria-label="Remove person"
                          @click.prevent="removePersonInvolvedField(index)">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6">
                  <p class="text-muted small mb-2">Witnesses who can confirm the story</p>
                  <div class="input-list">
                    <div v-for="(witness, index) in form.incidentWitnesses" :key="`witness-${index}`"
                      class="row g-2 align-items-start mb-2">
                      <div class="col">
                        <FormFloatingInput type="text" label="Witness" :id="`witness-${index}`"
                          v-model="form.incidentWitnesses[index]" :optional="true" :has-error="errors.incidentWitnesses"
                          :error-message="errorMessages.incidentWitnesses.error" />
                      </div>
                      <div class="col-auto" v-if="form.incidentWitnesses.length > 1">
                        <button type="button" class="btn btn-link text-danger p-2 remove-btn"
                          aria-label="Remove witness" @click.prevent="removeWitnessField(index)">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="section-card">
              <div class="section-heading">
                <div>
                  <p class="eyebrow text-muted mb-1">Narrative & evidences</p>
                  <h5 class="mb-0 text-dark">Tell us what happened</h5>
                </div>
                <span class="text-muted small">Accepted: JPEG, PNG, PDF, MP4</span>
              </div>
              <div class="row g-4">
                <div class="col-12">
                  <FormTextAreaInput label="Detailed Description" id="incident-description"
                    v-model="form.incidentDescription" :error-message="errorMessages.incidentDescription.error"
                    :has-error="errors.incidentDescription" :is-resizeable="false" max-rows="6" />
                </div>
                <div class="col-12">
                  <UploadFiles v-model="form.evidences" />
                  <p class="text-muted small mt-2 mb-0">Attach up to 10 files. Evidence strengthens your report.</p>
                </div>
              </div>
            </div>

            <div class="text-md-end">
              <FormButton label="Submit Report" />
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.create-report-wrapper {
  background: #f8fbff;
}

.hero-card {
  border-radius: 1.5rem;
  border: 1px solid rgba(13, 110, 253, 0.15);
  background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(25, 135, 84, 0.05));
}

.tip-card {
  border-radius: 1.5rem;
  border: 1px solid #e3e9fb;
  background: #fff;
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.08);
}

.form-card {
  border-radius: 1.5rem;
}

.section-card {
  border-radius: 1.25rem;
  border: 1px solid #e7ecfb;
  padding: 1.5rem;
  background: #fff;
}

.section-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.eyebrow {
  letter-spacing: 0.08em;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.stat-tile {
  border-radius: 1rem;
  background-color: rgba(255, 255, 255, 0.85);
  border: 1px dashed rgba(13, 110, 253, 0.25);
  padding: 1rem 1.25rem;
  min-height: 100%;
}

.pill {
  border-radius: 999px;
  padding: 0.5rem 1rem;
  font-size: 0.85rem;
}

.input-list .remove-btn {
  line-height: 1;
  border-radius: 50%;
}

.input-list .remove-btn:hover {
  background-color: rgba(220, 53, 69, 0.1);
}

@media (max-width: 767.98px) {

  .hero-card,
  .tip-card,
  .section-card {
    padding: 1.25rem;
  }

  .section-heading {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
