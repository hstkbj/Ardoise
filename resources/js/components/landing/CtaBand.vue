<template>
  <section class="mx-auto max-w-6xl px-5">
    <div class="relative overflow-hidden rounded-[32px] bg-brand-600 px-8 py-14 text-center text-white sm:px-16">
      <div class="absolute -top-16 -left-16 size-56 rounded-full bg-white/5" aria-hidden="true" />
      <div class="absolute -right-10 -bottom-20 size-56 rounded-full bg-sun/20" aria-hidden="true" />
      <h2 class="relative text-3xl font-semibold tracking-tight sm:text-4xl">Prêt à passer à une école <span class="text-sun">plus sereine</span> ?</h2>
      <p class="relative mx-auto mt-4 max-w-xl text-brand-100">Nous préparons votre espace, importons vos élèves et formons vos équipes. Comptez une semaine.</p>
      <div class="relative mt-8 flex flex-wrap justify-center gap-3">
        <RouterLink to="/contact" class="btn btn-lg rounded-full bg-white text-brand-700 hover:bg-cream">Demander une démo</RouterLink>
        <RouterLink to="/pricing" class="btn btn-lg rounded-full border border-white/30 text-white hover:bg-white/10">Voir les tarifs</RouterLink>
      </div>
    </div>
  </section>
</template>
