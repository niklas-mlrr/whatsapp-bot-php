<template>
  <div class="flex w-full mb-1" :class="[bubbleAlign, { 'opacity-60': message.isSending }]">
    <div v-if="!isMe" class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold text-sm mr-2">
      {{ senderInitials }}
    </div>
    <div class="flex flex-col max-w-[70%]">
      <div :class="bubbleClass">
        <template v-if="message.type === 'text'">
          <span class="whitespace-pre-line">{{ message.content }}</span>
        </template>
        <template v-else-if="message.type === 'image'">
          <img :src="imageSrc" alt="Image" class="max-h-48 rounded-lg" />
          <span v-if="message.content" class="block mt-1 whitespace-pre-line">{{ message.content }}</span>
        </template>
        <template v-else>
          <span class="italic text-gray-400">Unsupported message type</span>
        </template>
        <div class="flex items-center justify-end gap-1 mt-1">
          <svg v-if="message.isSending" class="animate-spin h-3 w-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-xs text-gray-400">{{ formattedTime }}</span>
          <span v-if="message.isSending" class="text-xs text-gray-400 italic">sending...</span>
        </div>
      </div>
    </div>
    <div v-if="isMe" class="flex-shrink-0 w-8 h-8 rounded-full bg-green-300 flex items-center justify-center text-green-700 font-bold text-sm ml-2">
      {{ senderInitials }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ message: any }>()

const isMe = computed(() => props.message.sender === 'me' || props.message.isMe)

const senderInitials = computed(() => {
  if (!props.message.sender) return '?'
  return props.message.sender
    .split(' ')
    .map((n: string) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const formattedTime = computed(() => {
  const d = new Date(props.message.sending_time)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
})

const imageSrc = computed(() => {
  if (props.message.media) {
    // If already a full URL (e.g. http...), use as is; else, prepend /storage/
    if (/^https?:\/\//.test(props.message.media)) return props.message.media
    return `/storage/${props.message.media}`
  }
  if (props.message.content) return `/storage/${props.message.content}`
  return ''
})

const bubbleAlign = computed(() =>
  isMe.value ? 'justify-end' : 'justify-start'
)
const bubbleClass = computed(() =>
  [
    'px-4 py-2 rounded-lg shadow text-sm break-words',
    isMe.value ? 'bg-green-200 text-green-900 self-end' : 'bg-gray-100 text-gray-900 self-start'
  ].join(' ')
)
</script> 