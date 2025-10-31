<script setup lang="ts">
import AppLogo from '../common/AppLogo/AppLogo.vue';
import HamburgerButton from '../common/HamburgerButton/HamburgerButton.vue';
import SlidingNavbar from '../common/SlidingNavbar/SlidingNavbar.vue';

const navItems = [
  {
    path: '/',
    label: 'Home'
  },
  {
    path: '/about',
    label: 'About'
  },
  {
    path: '/services',
    label: 'Services'
  },
]

const loggedNav = [
  {
    path: '/',
    label: 'Home'
  },

  {
    path: '/',
    label: 'File A Blotter'
  },

  {
    path: '/',
    label: 'View Status'
  },

  {
    path: '/',
    label: 'Certifications'
  },

  {
    path: '/',
    label: 'My Account'
  },

  {
    path: '/',
    label: 'Sign Out'
  },
]
</script>
<template>
  <nav class="navbar navbar-expand-md navbar-dark bg-primary-gradient">
    <div class="container d-flex align-items-center justify-between">
      <AppLogo />
      <HamburgerButton />
      <SlidingNavbar :navLinks="navItems" title="Navigation Menu" />
    </div>
  </nav>
</template>
