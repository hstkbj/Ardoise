import { onMounted, ref } from 'vue';
import { publicApi } from '@/services/api';

/**
 * Anti-spam des formulaires publics (côté serveur : App\Support\SpamGuard).
 * `fields()` est à fusionner dans la requête ; `refresh()` redemande un jeton après un échec.
 */
export function useSpamGuard() {
  const token = ref('');
  const honeypot = ref('');
  const captcha = ref('');
  const siteKey = window.__ARDOISE__?.turnstileSiteKey || null;

  async function refresh() {
    try {
      token.value = (await publicApi.formToken()).token;
    } catch {
      token.value = '';
    }
  }

  onMounted(refresh);

  const fields = () => ({
    form_token: token.value,
    website: honeypot.value,
    ...(captcha.value ? { 'cf-turnstile-response': captcha.value } : {}),
  });

  return { honeypot, captcha, siteKey, fields, refresh };
}
