<template>
  <div class="h-full overflow-y-auto">
    <div ref="messageContainer" class="min-h-full flex flex-col justify-end px-6 py-4">
      <div class="w-full">
        <div v-if="loading" class="text-blue-500 py-4 text-center">Loading messages...</div>
        <div v-else-if="error" class="text-red-500 py-4 text-center">{{ error }}</div>
        <div v-else-if="sortedMessages.length === 0" class="text-gray-500 py-8 text-center">
          No messages yet. Send a message to start the conversation!
        </div>
        <div v-else class="space-y-2">
          <MessageItem v-for="msg in sortedMessages" :key="msg.id" :message="msg" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, defineExpose, computed, nextTick } from 'vue'
import { fetchMessages } from '../api/messages'
import MessageItem from './MessageItem.vue'

const props = defineProps<{ chat: string | null }>()

const messages = ref<any[]>([])
const temporaryMessage = ref<any | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const messageContainer = ref<HTMLElement | null>(null)
const pollingInterval = ref<number | null>(null)
const POLLING_INTERVAL_MS = 3000 // Poll every 3 seconds

// Computed property to sort messages by creation time (oldest first, newest at bottom)
// and include temporary message if present
const sortedMessages = computed(() => {
  const allMessages = [...messages.value]
  if (temporaryMessage.value) {
    allMessages.push(temporaryMessage.value)
  }
  return allMessages.sort((a, b) => {
    const timeA = new Date(a.created_at || a.sending_time).getTime()
    const timeB = new Date(b.created_at || b.sending_time).getTime()
    return timeA - timeB
  })
})

async function loadMessages(isPolling = false) {
  if (!props.chat) {
    messages.value = []
    stopPolling()
    return
  }
  
  // Don't show loading indicator when polling
  if (!isPolling) {
    loading.value = true
  }
  error.value = null
  
  try {
    const response = await fetchMessages({ chat: props.chat })
    const newMessages = response.data.data
    
    // Check if we have new messages
    const hasNewMessages = newMessages.length > messages.value.length ||
      newMessages.some(newMsg => !messages.value.find(existingMsg => existingMsg.id === newMsg.id))
    
    messages.value = newMessages
    
    // Auto-scroll to bottom if there are new messages
    if (hasNewMessages && messageContainer.value) {
      await nextTick()
      scrollToBottom()
    }
  } catch (e: any) {
    error.value = e?.message || 'Failed to load messages.'
  } finally {
    if (!isPolling) {
      loading.value = false
    }
  }
}

function scrollToBottom() {
  if (messageContainer.value) {
    messageContainer.value.scrollTop = messageContainer.value.scrollHeight
  }
}

function startPolling() {
  stopPolling() // Clear any existing interval
  if (props.chat) {
    pollingInterval.value = window.setInterval(() => {
      loadMessages(true)
    }, POLLING_INTERVAL_MS)
  }
}

function stopPolling() {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

function addTemporaryMessage(message: any) {
  temporaryMessage.value = message
  nextTick(() => {
    scrollToBottom()
  })
}

function removeTemporaryMessage() {
  temporaryMessage.value = null
}

defineExpose({ 
  reload: () => loadMessages(false),
  scrollToBottom,
  addTemporaryMessage,
  removeTemporaryMessage
})

onMounted(async () => {
  await loadMessages()
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})

watch(() => props.chat, async () => {
  await loadMessages()
  startPolling()
})
</script>
