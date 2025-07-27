<template>
  <div class="flex flex-col h-full">
    <!-- Loading indicator -->
    <div v-if="loading && !messages.length" class="flex-1 flex items-center justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
    </div>
    
    <!-- Messages container -->
    <div 
      v-else
      ref="scrollContainer" 
      class="flex-1 overflow-y-auto p-4 space-y-4"
      @scroll="handleScroll"
    >
      <!-- Load more messages button -->
      <div v-if="hasMoreMessages && !loading" class="flex justify-center">
        <button 
          @click="loadMoreMessages"
          class="px-4 py-2 text-sm text-blue-600 hover:text-blue-800"
          :disabled="isLoadingMore"
        >
          {{ isLoadingMore ? 'Loading...' : 'Load older messages' }}
        </button>
      </div>

      <!-- Messages list -->
      <template v-for="message in sortedMessages" :key="message.id || message.temp_id">
        <div 
          class="message-item"
          :class="{
            'justify-end': isCurrentUser(message),
            'justify-start': !isCurrentUser(message)
          }"
        >
          <div 
            class="message-bubble"
            :class="{
              'bg-blue-500 text-white': isCurrentUser(message),
              'bg-gray-100': !isCurrentUser(message)
            }"
          >
            <!-- Sender name for group chats -->
            <div 
              v-if="isGroupChat && !isCurrentUser(message)" 
              class="text-xs font-medium mb-1"
              :class="{
                'text-blue-100': isCurrentUser(message),
                'text-gray-600': !isCurrentUser(message)
              }"
            >
              {{ getSenderName(message) }}
            </div>
            
            <!-- Message content -->
            <div class="message-content">
              {{ message.content }}
            </div>
            
            <!-- Message metadata -->
            <div 
              class="message-meta flex items-center justify-end space-x-1 mt-1 text-xs"
              :class="{
                'text-blue-100': isCurrentUser(message),
                'text-gray-500': !isCurrentUser(message)
              }"
            >
              <span class="time">{{ formatTime(message.created_at) }}</span>
              <span v-if="isCurrentUser(message)" class="status">
                <template v-if="message.status === 'sending'">
                  <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </template>
                <template v-else-if="message.status === 'sent' || message.status === 'delivered'">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </template>
                <template v-else-if="message.status === 'read'">
                  <svg class="w-3 h-3 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L9 12.586l7.293-7.293a1 1 0 011.414 1.414l-8 8z" />
                  </svg>
                </template>
                <template v-else-if="message.status === 'failed'">
                  <svg class="w-3 h-3 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </template>
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- Typing indicator -->
      <div v-if="isTyping" class="flex items-center space-x-2 p-2">
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce"></div>
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
        <span class="text-sm text-gray-500">typing...</span>
      </div>
      
      <!-- New message indicator -->
      <div v-if="hasNewMessages" class="new-messages-indicator">
        <button @click="scrollToBottom({ behavior: 'smooth' })">
          New messages
        </button>
      </div>
      
      <!-- Scroll to bottom button -->
      <button
        v-if="!isScrolledToBottom"
        @click="scrollToBottom({ behavior: 'smooth' })"
        class="fixed bottom-24 right-6 bg-blue-500 text-white rounded-full p-3 shadow-lg hover:bg-blue-600 transition-colors"
      >
        ↓
      </button>
    </div>

      <!-- Typing indicator -->
      <div v-if="isTyping" class="flex items-center space-x-2 p-2">
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce"></div>
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
        <span class="text-sm text-gray-500">typing...</span>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue';
import axios from 'axios';
import { useWebSocket } from '@/services/websocket';

// Types
interface Message {
  id: string;
  chat_id: string;
  sender_id: string;
  content: string;
  created_at: string;
  updated_at: string;
  status: 'sending' | 'sent' | 'delivered' | 'read' | 'failed';
  read_by?: string[];
  temp_id?: string;
}

interface ChatMember {
  id: string;
  name: string;
}

interface TypingEvent {
  user_id: string;
  typing: boolean;
}

interface ReadReceiptEvent {
  message_id: string;
  user_id: string;
  read_at?: string;
}

const props = defineProps({
  chat: {
    type: String,
    required: true
  },
  isGroupChat: {
    type: Boolean,
    default: false
  },
  currentUser: {
    type: Object as () => { id: string; name: string },
    required: true
  },
  members: {
    type: Array as () => ChatMember[],
    default: () => []
  }
});

const emit = defineEmits(['load-more', 'message-read', 'typing']);

// State
const messages = ref<Message[]>([]);
const loading = ref(true);
const isLoadingMore = ref(false);
const hasMoreMessages = ref(true);
const lastMessageId = ref<string | null>(null);
const scrollContainer = ref<HTMLElement | null>(null);
const isScrolledToBottom = ref(true);
const typingUsers = ref<Record<string, boolean>>({});
const typingTimeouts = ref<Record<string, number>>({});
const isConnected = ref(false);
const reconnectAttempts = ref(0);
const maxReconnectAttempts = 5;
const reconnectTimeout = ref<number | null>(null);
const pollInterval = ref<number | null>(null);
const hasNewMessages = ref(false);
const isTyping = ref(false);

// WebSocket composable
const { 
  connect: connectWebSocket, 
  disconnect: disconnectWebSocket, 
  listenForNewMessages, 
  listenForTyping, 
  listenForReadReceipts,
  notifyTyping,
  markAsRead
} = useWebSocket();

// Computed
const sortedMessages = computed(() => {
  return [...messages.value].sort((a, b) => {
    // Handle temporary messages (sending in progress)
    if (a.temp_id && !b.temp_id) return -1;
    if (!a.temp_id && b.temp_id) return 1;
    
    const dateA = new Date(a.created_at).getTime();
    const dateB = new Date(b.created_at).getTime();
    return dateA - dateB; // Oldest first (newest at bottom)
  });
});

// Helper functions
const isCurrentUser = (message: Message): boolean => {
  return message.sender_id === props.currentUser.id;
};

const getSenderName = (message: Message): string => {
  if (!props.isGroupChat || isCurrentUser(message)) return '';
  const sender = props.members.find(m => m.id === message.sender_id);
  return sender?.name || 'Unknown';
};

const formatTime = (dateString: string): string => {
  const date = new Date(dateString);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

// WebSocket event handlers
const handleNewMessage = (message: Message) => {
  // Prevent duplicates
  if (!messages.value.some(m => m.id === message.id || m.temp_id === message.temp_id)) {
    messages.value.push(message);
    lastMessageId.value = message.id;
    
    // Auto-scroll if at bottom
    if (isScrolledToBottom.value) {
      nextTick(() => scrollToBottom({ behavior: 'smooth' }));
    } else {
      hasNewMessages.value = true;
    }
  }
};

const handleTyping = (event: TypingEvent) => {
  if (event.user_id !== props.currentUser.id) {
    typingUsers.value = {
      ...typingUsers.value,
      [event.user_id]: event.typing
    };
    
    // Clear typing indicator after 3 seconds
    if (typingTimeouts.value[event.user_id]) {
      clearTimeout(typingTimeouts.value[event.user_id]);
    }
    
    if (event.typing) {
      typingTimeouts.value[event.user_id] = window.setTimeout(() => {
        typingUsers.value = {
          ...typingUsers.value,
          [event.user_id]: false
        };
      }, 3000);
    }
  }
};

const handleReadReceipt = (event: ReadReceiptEvent) => {
  messages.value = messages.value.map(msg => {
    if (msg.id === event.message_id && msg.status !== 'read') {
      return { ...msg, status: 'read' as const };
    }
    return msg;
  });
};

// Initialize WebSocket connection
const initWebSocket = async () => {
  try {
    await connectWebSocket();
    isConnected.value = true;
    reconnectAttempts.value = 0;
    // Listen for new messages
    listenForNewMessages(props.chat, (message: any) => handleNewMessage(message));
    // Listen for typing events
    listenForTyping(props.chat, (event: any) => handleTyping(event));
    // Listen for read receipts
    listenForReadReceipts(props.chat, (event: any) => handleReadReceipt(event));
  } catch (error) {
    console.error('WebSocket connection failed:', error);
    handleReconnect();
  }
};

// Handle reconnection
const handleReconnect = () => {
  if (reconnectAttempts.value < maxReconnectAttempts) {
    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts.value), 30000);
    reconnectAttempts.value++;
    
    reconnectTimeout.value = window.setTimeout(() => {
      initWebSocket();
    }, delay);
  }
};

// Load more messages
const loadMoreMessages = async () => {
  if (isLoadingMore.value || !hasMoreMessages.value) return;
  
  isLoadingMore.value = true;
  
  try {
    const response = await axios.get(`/api/chats/${props.chat}/messages`, {
      params: {
        before: lastMessageId.value,
        limit: 20
      }
    });
    
    const newMessages = response.data.data || [];
    
    if (newMessages.length > 0) {
      // Add new messages to the beginning of the array
      messages.value = [...newMessages, ...messages.value];
      lastMessageId.value = newMessages[0].id;
      
      // Check if there are more messages to load
      hasMoreMessages.value = newMessages.length === 20;
      
      // Wait for DOM to update with new messages
      await nextTick();
      
      // Maintain scroll position
      if (scrollContainer.value) {
        const firstMessageElement = scrollContainer.value.querySelector('.message-item');
        if (firstMessageElement) {
          firstMessageElement.scrollIntoView(true);
        }
      }
    } else {
      hasMoreMessages.value = false;
    }
  } catch (error) {
    console.error('Error loading more messages:', error);
  } finally {
    isLoadingMore.value = false;
  }
};

// Handle scroll events
const handleScroll = () => {
  if (!scrollContainer.value) return;
  
  const { scrollTop, scrollHeight, clientHeight } = scrollContainer.value;
  const isAtBottom = scrollHeight - (scrollTop + clientHeight) < 50;
  
  isScrolledToBottom.value = isAtBottom;
  
  // If scrolled to bottom and there were new messages, mark as read
  if (isAtBottom && hasNewMessages.value) {
    hasNewMessages.value = false;
    markVisibleMessagesAsRead();
  }
  
  // Load more messages when scrolling near the top
  if (scrollTop < 100 && hasMoreMessages.value && !isLoadingMore.value) {
    loadMoreMessages();
  }
};

// Scroll to bottom of the container
const scrollToBottom = (options?: ScrollToOptions) => {
  if (scrollContainer.value) {
    scrollContainer.value.scrollTo({
      top: scrollContainer.value.scrollHeight,
      ...options
    });
    hasNewMessages.value = false;
  }
};

// Mark visible messages as read
const markVisibleMessagesAsRead = () => {
  const unreadMessages = messages.value.filter(
    msg => !msg.read_by?.includes(props.currentUser.id) && 
           msg.sender_id !== props.currentUser.id
  );
  
  if (unreadMessages.length > 0) {
    const messageIds = unreadMessages.map(msg => msg.id).filter(Boolean) as string[];
    markMessagesAsRead(messageIds);
  }
};

// Start polling for new messages (fallback if WebSocket fails)
const startPolling = () => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value);
  }
  pollInterval.value = window.setInterval(() => {
    if (document.visibilityState === 'visible' && !isConnected.value) {
      fetchLatestMessages();
    }
  }, 30000);
};

// Fetch latest messages
const fetchLatestMessages = async () => {
  try {
    const response = await axios.get(`/api/chats/${props.chat}/messages/latest`, {
      params: {
        after: messages.value[messages.value.length - 1]?.id
      }
    });
    
    const newMessages = response.data.data || [];
    
    if (newMessages.length > 0) {
      // Add new messages to the end of the array
      messages.value = [...messages.value, ...newMessages];
      
      // If user is scrolled to bottom, auto-scroll to new messages
      if (isScrolledToBottom.value) {
        nextTick(() => {
          scrollToBottom();
        });
      } else {
        hasNewMessages.value = true;
      }
    }
  } catch (error) {
    console.error('Error fetching latest messages:', error);
  }
};

// Mark messages as read
const markMessagesAsRead = async (messageIds: string[]) => {
  if (messageIds.length === 0) return;
  
  try {
    await axios.post('/api/messages/read', {
      message_ids: messageIds
    });
    
    // Update local message status
    messages.value.forEach(message => {
      if (messageIds.includes(message.id) && message.status !== 'read') {
        message.status = 'read';
        message.read_by = message.read_by || [];
        if (!message.read_by.includes(props.currentUser.id)) {
          message.read_by.push(props.currentUser.id);
        }
      }
    });
    
    emit('message-read', messageIds);
  } catch (error) {
    console.error('Error marking messages as read:', error);
  }
};

// Lifecycle hooks
function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    markVisibleMessagesAsRead();
  }
}

function handleWindowFocus() {
  markVisibleMessagesAsRead();
}

onMounted(() => {
  // Initialize WebSocket connection
  initWebSocket();
  // Fallback: Start polling only if WebSocket is not connected
  startPolling();
  // Add visibility change listener
  document.addEventListener('visibilitychange', handleVisibilityChange);
  // Add focus listener for marking messages as read
  window.addEventListener('focus', handleWindowFocus);
});

onUnmounted(() => {
  // Cleanup
  if (reconnectTimeout.value) {
    clearTimeout(reconnectTimeout.value);
  }
  if (pollInterval.value) {
    clearInterval(pollInterval.value);
  }
  Object.values(typingTimeouts.value).forEach(clearTimeout);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  window.removeEventListener('focus', handleWindowFocus);
  if (isConnected.value) {
    disconnectWebSocket();
  }
});


// Handle WebSocket disconnection and reconnection
const handleDisconnect = () => {
  isConnected.value = false;
  
  if (reconnectAttempts.value < maxReconnectAttempts) {
    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts.value), 30000); // Exponential backoff with max 30s
    reconnectAttempts.value++;
    
    reconnectTimeout.value = window.setTimeout(() => {
      console.log(`Attempting to reconnect (${reconnectAttempts.value}/${maxReconnectAttempts})...`);
      initWebSocket();
    }, delay);
  } else {
    console.error('Max reconnection attempts reached');
    // Notify user about connection issues
  }
};

// Set up WebSocket event listeners
const setupWebSocketListeners = () => {
  if (!props.chat) return;
  
  // Listen for new messages
  listenForNewMessages(props.chat, (message: any) => {
    const messageExists = messages.value.some(m => m.id === message.id || m.temp_id === message.temp_id);
    if (!messageExists) {
      messages.value = [...messages.value, message];
      
      // Auto-scroll if user is at bottom
      if (isScrolledToBottom.value) {
        nextTick(() => {
          scrollToBottom({ behavior: 'smooth' });
        });
      }
    }
  });
  
  // Listen for typing indicators
  listenForTyping(props.chat, (event: any) => {
    if (event.typing) {
      typingUsers.value[event.user_id] = true;
      
      // Clear previous timeout if exists
      if (typingTimeouts.value[event.user_id]) {
        clearTimeout(typingTimeouts.value[event.user_id]);
      }
      
      // Set timeout to remove typing indicator after 3 seconds
      typingTimeouts.value[event.user_id] = window.setTimeout(() => {
        delete typingUsers.value[event.user_id];
        typingUsers.value = { ...typingUsers.value };
      }, 3000);
    } else {
      delete typingUsers.value[event.user_id];
    }
    typingUsers.value = { ...typingUsers.value };
  });
  
  // Listen for read receipts
  listenForReadReceipts(props.chat, (event: any) => {
    messages.value = messages.value.map(msg => {
      if (msg.id === event.message_id) {
        return {
          ...msg,
          status: 'read' as const,
          read_by: [...(msg.read_by || []), event.user_id].filter((v, i, a) => a.indexOf(v) === i)
        };
      }
      return msg;
    });
  });
};

// Message type for better type safety
interface Message {
  id: string;
  chat_id: string;
  sender_id: string;
  content: string;
  created_at: string;
  status: 'sending' | 'sent' | 'delivered' | 'read' | 'failed';
  temp_id?: string;
  sender?: {
    id: string;
    name: string;
  };
  read_by?: string[];
  sender_phone?: string;
  isSending?: boolean; // Add this to fix isSending errors
}

interface TypingEvent {
  user_id: string;
  typing: boolean;
}

interface ReadReceiptEvent {
  message_id: string;
  user_id: string;
}



// Fetch messages from API
const fetchMessages = async (params: { chatId: string; limit: number; before?: string }): Promise<{ messages: Message[]; hasMore: boolean }> => {
  try {
    const response = await axios.get('/api/messages', { params });
    return {
      messages: response.data.data,
      hasMore: response.data.meta.has_more
    };
  } catch (error) {
    console.error('Error fetching messages:', error);
    return { messages: [], hasMore: false };
  }
};

</script>

<style scoped>
/* Message container */
.message {
  margin-bottom: 1rem;
  transition: all 0.2s ease;
}

/* Typing indicator */
.typing-indicator {
  display: flex;
  align-items: center;
  padding: 0.5rem 1rem;
  margin: 0.25rem 0;
  border-radius: 1rem;
  background-color: #f3f4f6;
  width: fit-content;
  max-width: 80%;
}

.typing-dot {
  width: 0.5rem;
  height: 0.5rem;
  margin: 0 0.125rem;
  background-color: #9ca3af;
  border-radius: 50%;
  display: inline-block;
  animation: typingAnimation 1.4s infinite ease-in-out;
}

.typing-dot:nth-child(1) {
  animation-delay: 0s;
}

.typing-dot:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typingAnimation {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.6;
  }
  30% {
    transform: translateY(-0.25rem);
    opacity: 1;
  }
}

/* New messages indicator */
.new-messages-indicator {
  position: sticky;
  bottom: 1rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10;
  text-align: center;
  margin: 1rem 0;
}

.new-messages-indicator button {
  background-color: #3b82f6;
  color: white;
  border: none;
  border-radius: 1rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
}

.new-messages-indicator button:hover {
  background-color: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Scrollbar styling */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Message status indicators */
.message-status {
  display: inline-flex;
  align-items: center;
  margin-left: 0.25rem;
  font-size: 0.75rem;
  opacity: 0.8;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .message {
    margin-bottom: 0.75rem;
  }
  
  .typing-indicator {
    max-width: 90%;
  }
}

/* Animation for new messages */
@keyframes newMessage {
  from {
    opacity: 0;
    transform: translateY(0.5rem);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.message-enter-active {
  animation: newMessage 0.3s ease-out;
}
</style>

<style scoped>
.message-item {
  display: flex;
  margin-bottom: 0.5rem;
}

.message-bubble {
  border-radius: 0.5rem;
  padding-left: 1rem;
  padding-right: 1rem;
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
  max-width: 20rem;
  word-break: break-word;
  box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
}
@media (min-width: 768px) {
  .message-bubble { max-width: 28rem; }
}
@media (min-width: 1024px) {
  .message-bubble { max-width: 32rem; }
}
@media (min-width: 1280px) {
  .message-bubble { max-width: 42rem; }
}

.message-content {
  font-size: 0.875rem;
}

.message-meta {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-top: 0.25rem;
  font-size: 0.75rem;
  gap: 0.25rem;
}

.time {
  opacity: 0.75;
}

.status {
  display: flex;
  align-items: center;
}
</style>
