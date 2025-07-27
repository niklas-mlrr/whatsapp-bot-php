import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token') || null);
  const user = ref<any>(null);
  const isAuthenticated = computed(() => !!token.value);

  function setToken(authToken: string) {
    token.value = authToken;
    localStorage.setItem('auth_token', authToken);
  }

  function setUser(userData: any) {
    user.value = userData;
  }

  function logout() {
    token.value = null;
    user.value = null;
    localStorage.removeItem('auth_token');
  }

  return {
    token,
    user,
    isAuthenticated,
    setToken,
    setUser,
    logout
  };
});
