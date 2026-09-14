<script setup lang="ts"></script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <nav class="mx-auto flex max-w-4xl items-center gap-4 px-4 py-3">
        <span class="font-semibold">TurnoUno Admin</span>
        <RouterLink to="/" class="text-sm text-slate-600 hover:text-slate-900">Home</RouterLink>
        <RouterLink to="/health" class="text-sm text-slate-600 hover:text-slate-900">
          Health
        </RouterLink>
      </nav>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
