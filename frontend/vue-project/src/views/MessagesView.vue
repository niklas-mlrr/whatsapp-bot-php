<template>
  <div class="min-h-screen bg-gray-100 flex flex-row">
    <!-- Sidebar -->
    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
      <div class="p-4 font-bold text-lg border-b border-gray-200">Chats</div>
      <div class="flex-1 overflow-y-auto">
        <div v-if="loadingChats" class="text-blue-500 p-4">Loading chats...</div>
        <div v-if="errorChats" class="text-red-500 p-4">{{ errorChats }}</div>
        <ul v-if="!loadingChats && !errorChats">
          <li v-for="chat in chats" :key="chat" @click="selectChat(chat)"
              :class="['cursor-pointer px-4 py-3 border-b border-gray-100 hover:bg-green-50', chat === selectedChat ? 'bg-green-100 font-bold' : '']">
            {{ chat }}
          </li>
        </ul>
      </div>
    </aside>
    <!-- Main chat area -->
    <main class="flex-1 flex flex-col">
      <!-- Chat header -->
      <div class="flex items-center gap-3 px-6 py-4 bg-white border-b border-gray-200 shadow-sm min-h-[64px]">
        <div class="w-10 h-10 rounded-full bg-green-300 flex items-center justify-center text-green-700 font-bold text-lg">
          <span v-if="selectedChat">{{ selectedChat.slice(0,2).toUpperCase() }}</span>
        </div>
        <div class="flex flex-col">
          <span class="font-semibold text-lg text-gray-900">{{ selectedChat || 'Select a chat' }}</span>
          <span class="text-xs text-gray-400">Online</span>
        </div>
      </div>
      <div class="flex-1 flex flex-col justify-end">
        <MessageList ref="messageListRef" :chat="selectedChat" />
      </div>
      <!-- Message input -->
      <form @submit.prevent="sendMessageHandler" class="flex items-center gap-2 px-6 py-4 bg-white border-t border-gray-200 relative">
        <input
          v-model="input"
          :disabled="!selectedChat"
          type="text"
          placeholder="Type a message"
          class="flex-1 rounded-full border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-400 disabled:bg-gray-100 disabled:cursor-not-allowed"
        />
        <div class="relative">
          <button type="button" @click="openMenu" :disabled="!selectedChat"
            class="inline-block w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white ml-2 focus:outline-none disabled:bg-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          </button>
          <div v-if="showMenu" class="absolute left-0 bottom-12 z-10 bg-white border border-gray-200 rounded shadow-lg min-w-[140px]">
            <ul>
              <li @click="selectAddImage" class="px-4 py-2 hover:bg-green-50 cursor-pointer">Add image</li>
              <!-- Future: <li class='px-4 py-2 hover:bg-green-50 cursor-pointer'>Create poll</li> -->
            </ul>
          </div>
        </div>
        <input id="image-upload-input" type="file" accept="image/*" class="hidden" @change="onImageChange" :disabled="!selectedChat" />
        <button
          type="submit"
          :disabled="(!input && !imagePath) || !selectedChat"
          class="bg-green-500 text-white rounded-full px-6 py-2 font-semibold disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          Send
        </button>
      </form>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import MessageList from '../components/MessageList.vue'
import { fetchChats, sendMessage, uploadImage } from '../api/messages'

const chats = ref<string[]>([])
const loadingChats = ref(false)
const errorChats = ref<string | null>(null)
const selectedChat = ref<string | null>(null)
const input = ref('')
const imageFile = ref<File | null>(null)
const imagePath = ref<string | null>(null)
const imageMimetype = ref<string | null>(null)
const showMenu = ref(false)

const messageListRef = ref<any>(null)

function selectChat(chat: string) {
  selectedChat.value = chat
}

async function sendMessageHandler() {
  if ((!input.value && !imagePath.value) || !selectedChat.value) return
  try {
    let payload: any = {
      sender: 'me',
      chat: selectedChat.value,
      type: imagePath.value ? 'image' : 'text',
      content: input.value,
    }
    if (imagePath.value) {
      payload.media = imagePath.value
      payload.mimetype = imageMimetype.value
      payload.content = input.value // optional caption
    }
    await sendMessage(payload)
    input.value = ''
    imageFile.value = null
    imagePath.value = null
    imageMimetype.value = null
    // Reload messages for the current chat
    if (messageListRef.value && messageListRef.value.reload) {
      messageListRef.value.reload()
    }
  } catch (e) {
    alert('Failed to send message')
  }
}

async function onImageChange(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (files && files[0]) {
    imageFile.value = files[0]
    // Upload the image immediately
    try {
      const res = await uploadImage(imageFile.value)
      imagePath.value = res.data.path
      imageMimetype.value = imageFile.value.type
    } catch (err) {
      alert('Image upload failed')
      imageFile.value = null
      imagePath.value = null
      imageMimetype.value = null
    }
  }
}

function openMenu() {
  showMenu.value = !showMenu.value
}

function selectAddImage() {
  showMenu.value = false
  document.getElementById('image-upload-input')?.click()
}

onMounted(async () => {
  loadingChats.value = true
  errorChats.value = null
  try {
    const response = await fetchChats()
    chats.value = response.data.data
    if (chats.value.length > 0) {
      selectedChat.value = chats.value[0]
    }
  } catch (e: any) {
    errorChats.value = e?.message || 'Failed to load chats.'
  } finally {
    loadingChats.value = false
  }
})
</script>
