import { ref } from 'vue';

/** Validation minimale côté client + réception des erreurs 422 de Laravel. */
export function useFormErrors() {
  const errors = ref({});

  function validateRequired(fields, values, { isEdit = false } = {}) {
    const out = {};
    fields.forEach((f) => {
      const v = values[f.key];
      const empty = v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);
      if (f.required && empty && !(f.type === 'file' && isEdit)) out[f.key] = 'Ce champ est obligatoire.';
      if (f.type === 'email' && v && !/^\S+@\S+\.\S+$/.test(v)) out[f.key] = 'Adresse e-mail invalide.';
    });
    errors.value = out;
    return Object.keys(out).length === 0;
  }

  function setServerErrors(serverErrors = {}) {
    errors.value = Object.fromEntries(Object.entries(serverErrors).map(([k, v]) => [k.split('.')[0], Array.isArray(v) ? v[0] : v]));
  }

  return { errors, validateRequired, setServerErrors };
}
