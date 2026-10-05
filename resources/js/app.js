import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
app.use(createPinia());

// Session existante (cookie Sanctum) récupérée avant la première navigation
const auth = useAuthStore();
auth.init().finally(() => {
  app.use(router);
  app.mount('#app');
});
