import { computed, ref } from 'vue';

const KEY = 'ardoise_cookie_consent';

function read() {
  try {
    const value = window.localStorage.getItem(KEY);
    return value === 'accepted' || value === 'refused' ? value : null;
  } catch {
    return null;
  }
}

function write(value) {
  try {
    window.localStorage.setItem(KEY, value);
  } catch {
    // navigation privée ou stockage bloqué : le choix vaut pour la session en cours
  }
}

const choice = ref(read());
const reopened = ref(false);

/**
 * Consentement aux cookies facultatifs. Ardoise n'en dépose aucun aujourd'hui (seuls la session
 * et la protection CSRF, indispensables) : tout futur outil de mesure doit être conditionné à
 * `allowsOptional` avant de se charger.
 */
export function useCookieConsent() {
  return {
    choice,
    visible: computed(() => choice.value === null || reopened.value),
    allowsOptional: computed(() => choice.value === 'accepted'),
    accept() {
      choice.value = 'accepted';
      reopened.value = false;
      write('accepted');
    },
    refuse() {
      choice.value = 'refused';
      reopened.value = false;
      write('refused');
    },
    reopen() {
      reopened.value = true;
    },
  };
}
