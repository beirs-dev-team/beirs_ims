<script setup lang="ts">
import { ref } from 'vue';

const message = ref('')
</script>

<template>
    <h1>EDIT PROFILE</h1>

    <span>Full Name: </span>
    <textarea v-model="message" id="fullName"></textarea>

    <span>Updated Phone Number:</span>
    <textarea v-model="message" id="phoneNumber"></textarea>

    <span>Updated Sitio:</span>
    <textarea v-model="message" id="sitio"></textarea>
</template>