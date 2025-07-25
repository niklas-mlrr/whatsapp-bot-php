import { createRouter, createWebHistory } from 'vue-router'
import MessagesView from '../views/MessagesView.vue'

const routes = [
  { path: '/', name: 'Messages', component: MessagesView }
]

export default createRouter({
  history: createWebHistory(),
  routes
})
