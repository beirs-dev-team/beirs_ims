<script setup lang="ts">
import { computed } from 'vue';


const props = withDefaults(defineProps<{
  id: string
  options: Array<string>
  label?: string
  errorMessage?: string
  hasError?: boolean
  isDisabled?: boolean
  isOptional?: boolean
  isCapitalized?: boolean
}>(), {
  isOptional: false,
  isCapitalized: true
})

const model = defineModel<string>()

const capitalizeWords = (value: string) => {
  if (!value) return ''
  const lower = value.toLowerCase()
  return lower.replace(/\b\w/g, (char) => char.toUpperCase())
}

const formatOptionalLabel = computed(() => {
  return props.isOptional ? `${props.label}*` : `${props.label}`
})

const formattedOptions = computed(() =>
  props.options.map((option) => ({
    value: option,
    label: props.isCapitalized ? capitalizeWords(option) : option
  }))
)
</script>
<template>
  <slot v-if="$slots.default" />
  <div class="form-floating">
    <select :id :name="id" class="form-select" :class="{ 'is-invalid': hasError }" v-model="model"
      :disabled="isDisabled">
      <option value="" selected>...</option>
      <option :value="option.value" v-for="option in formattedOptions" :key="option.value">
        {{ option.label }}
      </option>
    </select>
    <label :for="id" class="form-label">{{ formatOptionalLabel }}</label>
    <div :id class="invalid-feedback" v-show="hasError">
      <small>{{ errorMessage }}</small>
    </div>
  </div>

</template>
