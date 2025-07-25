<template>
  <div class="flex flex-col h-full bg-white overflow-y-auto px-6 py-4">
    <div v-if="loading" class="text-blue-500">Loading...</div>
    <div v-if="error" class="text-red-500">{{ error }}</div>
    <div v-if="!loading && !error" class="flex flex-col gap-2">
      <MessageItem v-for="msg in messages" :key="msg.id" :message="msg" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, defineExpose } from 'vue'
import { fetchMessages } from '../api/messages'
import MessageItem from './MessageItem.vue'

const props = defineProps<{ chat: string | null }>()

const messages = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function loadMessages() {
  if (!props.chat) {
    messages.value = []
    return
  }
  loading.value = true
  error.value = null
  try {
    const response = await fetchMessages({ chat: props.chat })
    messages.value = response.data.data
  } catch (e: any) {
    error.value = e?.message || 'Failed to load messages.'
  } finally {
    loading.value = false
  }
}

defineExpose({ reload: loadMessages })

onMounted(loadMessages)
watch(() => props.chat, loadMessages)
</script>
