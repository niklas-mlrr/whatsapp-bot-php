<template>
  <div class="flex w-full mb-1" :class="bubbleAlign">
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
        <span class="block text-xs text-gray-400 text-right mt-1">{{ formattedTime }}</span>
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