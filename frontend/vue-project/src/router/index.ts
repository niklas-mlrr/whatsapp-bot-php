import { createRouter, createWebHistory, type RouteLocationNormalized, type NavigationGuardNext } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    redirect: '/messages'
  },
  {
    path: '/messages',
    name: 'Messages',
    component: () => import('@/views/MessagesView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/messages/:chatId?',
    name: 'Chat',
    component: () => import('@/views/MessagesView.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach((to: RouteLocationNormalized, from: RouteLocationNormalized, next: NavigationGuardNext) => {
  const authStore = useAuthStore()
  
  // If the route requires authentication and the user is not authenticated
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    // Redirect to login page
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } 
  // If the user is authenticated and tries to access login page
  else if (to.name === 'Login' && authStore.isAuthenticated) {
    // Redirect to home or the original requested URL
    const redirectPath = from.query.redirect as string || '/messages'
    next(redirectPath)
  } 
  // Otherwise, proceed with navigation
  else {
    next()
  }
})

export default router
