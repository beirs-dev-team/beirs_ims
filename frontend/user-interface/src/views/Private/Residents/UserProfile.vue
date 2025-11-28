<script setup lang="ts">
import { useEditUserAccount } from './composable/useEditUserAccount';
import DropdownInput from '@/components/common/DropdownInput/DropdownInput.vue';
import FormButton from '@/components/common/FormButton/FormButton.vue';
import { fetchSingleUserProfile, requestMobileVerificationOtp, updateUserAccount, verifyMobileVerificationOtp } from '@/Utils/userServices';
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';
import { Collapse, Tooltip } from 'bootstrap';
import type { AxiosError } from 'axios';
import type { ApiErrorResponse, CommonResponse, UpdateAccountRequestPayload, User } from '@/Types';
import { useSessionStore } from '@/Utils/store/useSessionStore';
import { useGlobalLoadingStore } from '@/Utils/store/useGlobalLoadingStore';
import { computeAge, formatName, orderedOptions, } from '@/Utils/helpers/formatters';
import { navigateToTopPage } from '@/Utils/helpers/common-helpers';
import FormFloatingInput from '@/components/common/FormFloatingInput/FormFloatingInput.vue';
import UploadFiles from '../BlotterReports/components/UploadFiles.vue';
import nationalId from '@/assets/images/nat-id.png';

const props = defineProps<{
  id: string
}>()

const responseData = ref<User>();
const hasError = ref<boolean>(false);
const useSession = useSessionStore();

const {
  form,
  errors,
  errorMessages,
  isNotEditableUser,
  roleOptions,
  addressOptions,
  isEditableSubmit,
  successResponse,
  setServerErrors,
  validateForm,
  setSuccessResponse,
  setisNotEditableUser
} = useEditUserAccount()

const navigation = useGlobalLoadingStore();
const isPasswordChangeable = ref<boolean>(false);
const localErrorMsg = ref<string>('')
const hasGovernmentId = ref<boolean>(false)
const govtIdTooltipButton = ref<HTMLElement | null>(null)
const govtIdCollapseRef = ref<HTMLElement | null>(null)
const originalMobileNumber = ref<string>('')
const isOtpModalVisible = ref(false)
const otpCode = ref('')
const otpMessage = ref('')
const otpError = ref('')
const isRequestingOtp = ref(false)
const isVerifyingOtp = ref(false)
const mobileVerificationPending = ref(false)
let govtIdTooltipInstance: Tooltip | null = null
let govtIdCollapseInstance: Collapse | null = null
const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.replace(/\/$/, '');
const storageBaseUrl =
  (import.meta.env.VITE_STORAGE_URL as string | undefined) ||
  (apiBaseUrl ? `${apiBaseUrl}/storage` : '/storage');

const primaryGovernmentIds = [
  'Valid Philippine Passport',
  'PhilSys ID / ePhilID',
  "Driver's License",
  'SSS ID / Unified Multi-Purpose ID (UMID)',
  'GSIS eCard',
  'Professional Regulation Commission (PRC) ID',
  'Integrated Bar of the Philippines (IBP) ID',
  "COMELEC Voter's ID",
  'Postal ID',
  'PWD ID',
  'Senior Citizen ID',
  "OFW/Seafarer's ID",
  'Firearms License ID (issued by PNP-FEO)',
  'National Bureau of Investigation (NBI) Clearance (with photo)',
]

const secondaryGovernmentIds = [
  'Birth Certificate (issued by PSA or LCR)',
  'Marriage Certificate (PSA)',
  'PhilHealth ID',
  'Pag-IBIG Loyalty Card',
  'Police Clearance',
  'Company ID (current employer, with signature of employer or authorized representative)',
]

const govtIdCollapseId = 'govt-id-accepted-list'
const isGovtIdListOpen = ref(false)

const govtIdentityTypeOption = [...primaryGovernmentIds, '...', ...secondaryGovernmentIds]

const governmentIdUrl = computed(() => {
  const doc = responseData.value?.profile?.government_identity;
  if (!doc?.storage_path) return '';
  const path = doc.storage_path;
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const normalizedBase = storageBaseUrl.endsWith('/') ? storageBaseUrl.slice(0, -1) : storageBaseUrl;
  return `${normalizedBase}/${path}`.replaceAll(/([^:]\/)\/+/g, '$1');
});

const governmentIdType = computed(() => {
  return responseData.value?.profile?.government_identity?.identity_type ?? ''
});

const hasMobileChanged = computed(() => form.mobileNumber !== originalMobileNumber.value)

const handleUpdateUserAccount = async () => {
  navigation.startNavigation();

  try {
    hasError.value = false
    setSuccessResponse(null)
    const isValid = validateForm();

    if (isValid) {
      hasError.value = false
      const mobileChanged = hasMobileChanged.value
      const requestPayload: UpdateAccountRequestPayload = {
        first_name: form.name.firstName,
        middle_name: form.name.middleName,
        last_name: form.name.lastName,
        email: form.email,
        role: form.role,
        street_address: form.streetAddress,
        address_line: form.addressLine,
        mobile_number: form.mobileNumber,
        date_of_birth: form.date_of_birth,
        government_identity_type: form.govtIdentityType,
        government_identity: form.governmentIdentity,
      }

      const trimmedPassword = form.password.trim()
      const trimmedPasswordConfirmation = form.passwordConfirmation.trim()

      if (trimmedPassword.length || trimmedPasswordConfirmation.length) {
        requestPayload.password = trimmedPassword
        requestPayload.password_confirmation = trimmedPasswordConfirmation
      }

      const response = await updateUserAccount(props.id, requestPayload)
      responseData.value = response.data
      hasGovernmentId.value = !!responseData.value?.profile?.government_identity
      originalMobileNumber.value = response.data.profile.mobile_number ?? ''
      mobileVerificationPending.value = mobileChanged || !response.data.profile.mobile_verified_at

      setSuccessResponse({
        status: response.status ?? 'success',
        message: response.message ?? 'User updated successfully.',
      })

      setisNotEditableUser()
      if (useSession.id === response.data.id) {
        const profile = response.data.profile
        useSession.updateUserName(formatName(profile.first_name, profile.middle_name, profile.last_name))
      }

      if (mobileChanged && response.data.profile.mobile_number) {
        await requestOtpForMobileVerification()
      }

    } else {
      hasError.value = true
    }
    globalThis.location.reload();
  } catch (error) {
    hasError.value = true
    setSuccessResponse(null)
    const axiosError = error as AxiosError<ApiErrorResponse>;
    const fallbackResponse = error as CommonResponse;

    setServerErrors(axiosError.response?.data?.errors, axiosError.response?.data?.message ?? fallbackResponse.message)
  } finally {
    navigation.endNavigation();
  }
}

const resetOtpState = () => {
  otpCode.value = ''
  otpMessage.value = ''
  otpError.value = ''
}

const requestOtpForMobileVerification = async () => {
  if (isRequestingOtp.value) return

  otpError.value = ''
  otpMessage.value = ''
  isRequestingOtp.value = true

  try {
    const response = await requestMobileVerificationOtp()
    otpMessage.value = response.message ?? 'OTP sent to your mobile number.'
    mobileVerificationPending.value = true
    isOtpModalVisible.value = true
  } catch (error) {
    const axiosError = error as AxiosError<ApiErrorResponse>
    otpError.value = axiosError.response?.data?.message || (error as Error)?.message || 'Failed to send OTP.'
  } finally {
    isRequestingOtp.value = false
  }
}

const verifyOtpForMobileChange = async () => {
  if (!otpCode.value.trim()) {
    otpError.value = 'Please enter the OTP code sent to your mobile number.'
    return
  }

  isVerifyingOtp.value = true
  otpError.value = ''

  try {
    const response = await verifyMobileVerificationOtp(otpCode.value.trim())
    otpMessage.value = response.message ?? 'Mobile number verified successfully.'
    mobileVerificationPending.value = false
    isOtpModalVisible.value = false
    resetOtpState()
    await fetchUserProfile()
  } catch (error) {
    const axiosError = error as AxiosError<ApiErrorResponse>
    otpError.value = axiosError.response?.data?.message || (error as Error)?.message || 'Failed to verify OTP.'
  } finally {
    isVerifyingOtp.value = false
  }
}

const fetchUserProfile = async () => {
  navigation.startNavigation()

  try {
    const response = await fetchSingleUserProfile(props.id)

    if (response.status !== 'success') {
      throw response;
    }

    responseData.value = response.data
    hasGovernmentId.value = !!responseData.value.profile?.government_identity;
    form.name.firstName = responseData.value.profile.first_name
    form.name.middleName = responseData.value.profile.middle_name
    form.name.lastName = responseData.value.profile.last_name

    form.email = responseData.value.email
    form.role = (responseData.value.role)
    form.streetAddress = responseData.value.profile.street_address
    form.addressLine = responseData.value.profile.address_line
    form.mobileNumber = responseData.value.profile.mobile_number
    form.date_of_birth = responseData.value.profile.date_of_birth
    originalMobileNumber.value = responseData.value.profile.mobile_number ?? ''
    mobileVerificationPending.value = !responseData.value.profile.mobile_verified_at

  } catch (error) {
    hasError.value = true

    const axiosError = error as AxiosError<ApiErrorResponse>;
    const fallbackResponse = error as CommonResponse;
    const apiErrorResponse = axiosError.response?.data?.errors

    setServerErrors(apiErrorResponse, fallbackResponse.message);
    localErrorMsg.value = fallbackResponse.message

  } finally {
    navigation.endNavigation();
  }
}

const handleShowPasswordChange = () => {
  isPasswordChangeable.value = !isPasswordChangeable.value;
  isNotEditableUser.value.password = !isNotEditableUser.value.password
  isNotEditableUser.value.passwordConfirmation = !isNotEditableUser.value.passwordConfirmation
  isEditableSubmit.value = !isEditableSubmit.value
}

const toggleGovtIdList = () => {
  govtIdCollapseInstance?.toggle();
}

const age = computed(() => {
  return computeAge(form.date_of_birth)
});

const isProfileOwner = computed(() => {
  return responseData.value?.id === useSession.id
})

const toTitleCase = (value?: string | null) => {
  if (!value) return ''
  const lower = value.toLowerCase()
  return lower.replaceAll(/\b\w/g, (char) => char.toUpperCase())
}

const fullName = computed(() => {
  if (!responseData.value) return ''
  const profile = responseData.value.profile
  return formatName(profile.first_name, profile.middle_name, profile.last_name)
})

const profileStatusClass = computed(() => {
  return responseData.value?.profile?.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-muted'
})

const profileStatusLabel = computed(() => {
  return responseData.value?.profile?.is_active ? 'Active Account' : 'Inactive Account'
})

const formattedAddress = computed(() => {
  const profile = responseData.value?.profile
  if (!profile) return 'No address on file'
  const parts = [profile.street_address, profile.address_line]
    .filter(Boolean)
    .map((part) => toTitleCase(part as string))
  return parts.length ? parts.join(', ') : 'No address on file'
})

watchEffect(() => {
  fetchUserProfile()
  setTimeout(() => {
    setSuccessResponse(null);
  }, 3000);
})

onMounted(() => {
  if (govtIdTooltipButton.value) {
    govtIdTooltipInstance = new Tooltip(govtIdTooltipButton.value);
  }

  if (govtIdCollapseRef.value) {
    const handleGovtIdShown = () => {
      isGovtIdListOpen.value = true
    }

    const handleGovtIdHidden = () => {
      isGovtIdListOpen.value = false
    }

    govtIdCollapseRef.value.addEventListener('shown.bs.collapse', handleGovtIdShown)
    govtIdCollapseRef.value.addEventListener('hidden.bs.collapse', handleGovtIdHidden)

    govtIdCollapseInstance = new Collapse(govtIdCollapseRef.value, { toggle: false })

    onBeforeUnmount(() => {
      govtIdCollapseRef.value?.removeEventListener('shown.bs.collapse', handleGovtIdShown)
      govtIdCollapseRef.value?.removeEventListener('hidden.bs.collapse', handleGovtIdHidden)
    })
  }
})

onBeforeUnmount(() => {
  govtIdTooltipInstance?.dispose();
  govtIdTooltipInstance = null;

  govtIdCollapseInstance?.dispose();
  govtIdCollapseInstance = null;
})
</script>
<template>
  <div class="my-5">
    <div
      class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-4 gap-3">
      <div>
        <p class="text-muted text-uppercase small mb-1">Resident Profile</p>
        <h2 class="fw-bold mb-1 text-capitalize">{{ fullName || 'Resident Profile' }}</h2>
        <p class="text-secondary small mb-0">Review and update necessary information in one place.</p>
      </div>
      <button class="btn btn-link text-decoration-none" type="button" @click="navigateToTopPage">
        <i class="bi bi-arrow-left me-2"></i>Back to Residents
      </button>
    </div>

    <div v-if="successResponse" class="alert alert-success" role="alert">
      {{ successResponse.message }}
    </div>
    <div v-else-if="hasError" class="alert alert-danger" role="alert">
      {{ localErrorMsg ?? '' }}
    </div>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <div class="avatar-placeholder me-3">
                <i class="bi bi-person-fill text-white"></i>
              </div>
              <div>
                <h5 class="mb-0 text-capitalize">{{ fullName || 'Resident' }}</h5>
                <span class="badge mt-2" :class="profileStatusClass">{{ profileStatusLabel }}</span>
              </div>
            </div>
            <div class="mb-3">
              <p class="text-muted small mb-1">Email</p>
              <p class="fw-semibold mb-0">{{ responseData?.email || '—' }}</p>
            </div>
            <div class="mb-3">
              <p class="text-muted small mb-1">Mobile Number</p>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <p class="fw-semibold mb-0">{{ responseData?.profile?.mobile_number || '—' }}</p>
                <span class="badge"
                  :class="mobileVerificationPending ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'">
                  {{ mobileVerificationPending ? 'Verification required' : 'Verified' }}
                </span>
              </div>
              <button v-if="mobileVerificationPending && responseData?.profile?.mobile_number"
                class="btn btn-link p-0 mt-1 text-sm" type="button" @click="requestOtpForMobileVerification">
                Verify this number via OTP
              </button>
            </div>
            <div class="mb-3">
              <p class="text-muted small mb-1">Address</p>
              <p class="fw-semibold mb-0">{{ formattedAddress }}</p>
            </div>
            <div class="mb-3">
              <p class="text-muted small mb-1">Role</p>
              <p class="fw-semibold mb-0 text-capitalize">{{ responseData?.role || '—' }}</p>
            </div>
            <div class="d-flex justify-content-between text-muted small">
              <span>Date of Birth</span>
              <span>{{ form.date_of_birth || '—' }} ({{ age }} yrs)</span>
            </div>
            <div class="mt-3" v-if="isProfileOwner">
              <button class="btn btn-outline-secondary w-100" type="button" @click="handleShowPasswordChange">
                <i class="bi bi-shield-lock me-2"></i>
                {{ isPasswordChangeable ? 'Cancel Password Update' : 'Change Password' }}
              </button>
            </div>
          </div>
        </div>

        <div class="card shadow-sm border-0">
          <!-- inside the Government ID card -->
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="fw-semibold mb-3 mb-lg-0">Government ID</h6>
              <button ref="govtIdTooltipButton" type="button" class="btn btn-link text-muted p-0"
                data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"
                data-bs-title="Upload a clear photo or scan of your valid government ID.<br>Accepted files: PNG, JPG, JPEG, PDF.<br>Uploading a new file will replace the existing ID.">
                <i class="bi bi-info-circle-fill"></i>
                <span class="visually-hidden">Government ID upload instructions</span>
              </button>
            </div>
            <div class="mb-3">
              <div class="d-flex align-items-center justify-content-between gap-2">
                <div>
                  <p class="text-muted small mb-0">Accepted IDs</p>
                  <p class="text-muted small mb-0">Tap to view the full list of options.</p>
                </div>
                <button class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" type="button"
                  :aria-expanded="isGovtIdListOpen" :aria-controls="govtIdCollapseId" @click="toggleGovtIdList">
                  <i class="bi" :class="isGovtIdListOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                  <span>{{ isGovtIdListOpen ? 'Hide' : 'View' }}</span>
                </button>
              </div>
              <div class="collapse mt-2" :id="govtIdCollapseId" ref="govtIdCollapseRef">
                <div class="bg-light border rounded p-3">
                  <p class="text-muted text-sm mb-1">List of acceptable Primary IDs</p>
                  <ul class="small mb-3 ps-3">
                    <li v-for="(id, index) in primaryGovernmentIds" :key="`primary-id-${index}`">{{ id }}</li>
                  </ul>
                  <p class="text-muted text-sm mb-1">(If no primary ID is available, At least one with photo and
                    signature, must be presented)</p>
                  <ul class="small mb-0 ps-3">
                    <li v-for="(id, index) in secondaryGovernmentIds" :key="`secondary-id-${index}`">{{ id }}</li>
                  </ul>
                </div>
              </div>
            </div>
            <div v-if="hasGovernmentId" class="border rounded text-center p-3 mb-2">
              <p v-if="governmentIdType" class="text-uppercase text-muted small fw-semibold mb-2">{{ governmentIdType }}
              </p>
              <img :src="governmentIdUrl || nationalId" alt="Government ID" class="img-fluid" />
              <p class="text-muted small mb-0 mt-2">Uploading a new file will replace the existing ID.</p>
            </div>
            <div class="mb-2" v-if="isProfileOwner && !isEditableSubmit">
              <DropdownInput :options="govtIdentityTypeOption" label="Type" id="govt-id-type"
                v-model="form.govtIdentityType" :error-message="errorMessages.govtIdentityType.error"
                :has-error="errors.govtIdentityType" :is-capitalized="false" />
            </div>
            <UploadFiles
              v-if="form.govtIdentityType && form.govtIdentityType !== '...' && [...primaryGovernmentIds, ...secondaryGovernmentIds].includes(form.govtIdentityType)"
              v-model="form.governmentIdentity" :has-error="errors.governmentIdentity"
              :error-message="errorMessages.governmentIdentity.error"
              :is-disabled="isNotEditableUser.governmentIdentity" accept=".png,.jpg,.jpeg,.pdf" :multiple="false" />
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Profile Details</h5>
            <div class="d-flex gap-2" v-if="isProfileOwner">
              <button class="btn btn-outline-danger btn-sm" type="button" @click.prevent="setisNotEditableUser">
                <i class="bi bi-pencil-square me-1"></i>{{ isNotEditableUser.name ? 'Enable Editing' : 'Cancel' }}
              </button>
            </div>
          </div>
          <div class="card-body">
            <form class="d-flex flex-column gap-3" @submit.prevent="handleUpdateUserAccount">
              <div>
                <p class="text-muted text-uppercase small mb-2">Basic Information</p>
                <div class="row g-2">
                  <div class="col-md-4">
                    <FormFloatingInput type="text" label="First Name" id="first_name" v-model="form.name.firstName"
                      :error-message="errorMessages.name.error" :is-disabled="isNotEditableUser.name" />
                  </div>
                  <div class="col-md-4">
                    <FormFloatingInput type="text" label="Middle Name" :optional="true" id="middle_name"
                      v-model="form.name.middleName" :is-disabled="isNotEditableUser.name" />
                  </div>
                  <div class="col-md-4">
                    <FormFloatingInput type="text" label="Last Name" id="last_name" v-model="form.name.lastName"
                      :has-error="errors.name" :error-message="errorMessages.name.error"
                      :is-disabled="isNotEditableUser.name" />
                  </div>
                </div>
              </div>

              <div>
                <p class="text-muted text-uppercase small mb-2">Account Details</p>
                <div class="row g-2 align-items-center">
                  <div class="col-md-6">
                    <FormFloatingInput type="email" label="Email Address" id="email" v-model="form.email"
                      :has-error="errors.email" :error-message="errorMessages.email.error"
                      :is-disabled="isNotEditableUser.email" :is-capitalized="false" />
                  </div>
                  <div class="col-md-3">
                    <FormFloatingInput v-if="!isNotEditableUser.dateOfBirth" type="date" label="Date Of Birth"
                      id="birthday" v-model="form.date_of_birth" :has-error="errors.date_of_birth"
                      :error-message="errorMessages.date_of_birth.error" />
                    <FormFloatingInput v-else type="text" label="Age" id="user-age" v-model="age"
                      :is-disabled="isNotEditableUser.dateOfBirth" />
                  </div>
                  <div class="col-md-3" style="margin-top: -1%;">
                    <DropdownInput :options="roleOptions" label="Role" id="select-roles" v-model="form.role"
                      :error-message="errorMessages.role.error" :has-error="errors.role"
                      :is-disabled="isNotEditableUser.role" />
                  </div>
                </div>
              </div>

              <div>
                <p class="text-muted text-uppercase small mb-2">Contact & Address</p>
                <div class="row g-2">
                  <div class="col-md-4">
                    <FormFloatingInput type="text" label="Mobile Number" id="phoneNumber" v-model="form.mobileNumber"
                      :has-error="errors.mobileNumber" :error-message="errorMessages.mobileNumber.error"
                      :is-disabled="isNotEditableUser.mobileNumber" />
                  </div>
                  <div class="col-md-4">
                    <DropdownInput :options="orderedOptions(addressOptions)" label="Sitio" id="sitio-name"
                      v-model="form.streetAddress" :error-message="errorMessages.streetAddress.error"
                      :has-error="errors.streetAddress" :is-disabled="isNotEditableUser.streetAddress" />
                  </div>
                  <div class="col-md-4">
                    <FormFloatingInput type="text" label="Address Line" id="address-line" v-model="form.addressLine"
                      :has-error="errors.addressLine" :error-message="errorMessages.addressLine.error"
                      :is-disabled="isNotEditableUser.mobileNumber" :optional="true" />
                  </div>
                </div>
              </div>

              <div v-show="isPasswordChangeable">
                <p class="text-muted text-uppercase small mb-2">Security</p>
                <div class="row g-2">
                  <div class="col-md-6">
                    <FormFloatingInput type="password" label="Password" id="password" v-model="form.password"
                      :has-error="errors.password" :error-message="errorMessages.password.error"
                      :is-disabled="isNotEditableUser.password" />
                  </div>
                  <div class="col-md-6">
                    <FormFloatingInput type="password" label="Confirm Password" id="passwordConfirm"
                      v-model="form.passwordConfirmation" :has-error="errors.passwordConfirmation"
                      :error-message="errorMessages.passwordConfirmation.error"
                      :is-disabled="isNotEditableUser.passwordConfirmation" />
                  </div>
                </div>
              </div>

              <div class="d-flex flex-column flex-md-row justify-content-center gap-3 mt-3" v-if="isProfileOwner">
                <FormButton label="Save Changes" :is-disabled="isEditableSubmit"
                  :btn-display="isEditableSubmit ? 'secondary' : 'primary'" />
                <FormButton type="button" label="Reset Form" btn-display="danger" :is-outlined="true"
                  @Click.prevent="setisNotEditableUser" />
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-if="isOtpModalVisible" class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="z-index: 1056;">
        <div class="modal-header">
          <h5 class="modal-title">Verify Mobile Number</h5>
          <button type="button" class="btn-close" aria-label="Close" @click="isOtpModalVisible = false"></button>
        </div>

        <div class="modal-body">
          <p class="text-muted mb-3">
            Enter the one-time password sent to your updated mobile number.
          </p>

          <FormFloatingInput type="text" label="OTP Code" id="otp_code" v-model="otpCode" :has-error="Boolean(otpError)"
            :error-message="otpError" />

          <p v-if="otpMessage" class="text-success small mb-0 mt-2">
            {{ otpMessage }}
          </p>
        </div>

        <div class="modal-footer d-flex justify-content-between">
          <button class="btn btn-outline-secondary" type="button" @click="requestOtpForMobileVerification"
            :disabled="isRequestingOtp">
            {{ isRequestingOtp ? 'Resending...' : 'Resend OTP' }}
          </button>

          <button class="btn btn-primary" type="button" @click="verifyOtpForMobileChange" :disabled="isVerifyingOtp">
            {{ isVerifyingOtp ? 'Verifying...' : 'Verify' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Backdrop -->
    <div class="modal-backdrop fade show" style="z-index: 1055;"></div>
  </div>

</template>

<style scoped>
.avatar-placeholder {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0d6efd, #6c63ff);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}
</style>
