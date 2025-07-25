<template>
  <span>
    <svg v-if="type === 'text'" xmlns="http://www.w3.org/2000/svg" class="inline-block" width="18" height="18" fill="none" viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16v2H4V4zm0 4h16v2H4V8zm0 4h10v2H4v-2zm0 4h10v2H4v-2z"/></svg>
    <svg v-else-if="type === 'image'" xmlns="http://www.w3.org/2000/svg" class="inline-block" width="18" height="18" fill="none" viewBox="0 0 24 24"><path fill="currentColor" d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zm-2 0H5V5h14v14zm-3-8l-2.5 3.01L11 11l-4 5h12l-3-4z"/></svg>
    <svg v-else xmlns="http://www.w3.org/2000/svg" class="inline-block" width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/><text x="12" y="16" text-anchor="middle" font-size="10" fill="currentColor">?</text></svg>
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{ type: string }>()
</script> 