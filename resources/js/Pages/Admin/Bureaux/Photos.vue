<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PhotoGallery from '@/Components/PhotoGallery.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    bureau: Object,
    images: Array,
})
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-base font-semibold text-gray-800">
                        Photos — {{ bureau.nom }}
                    </h1>
                    <p class="text-sm text-gray-500 font-mono mt-0.5">{{ bureau.code }}</p>
                </div>
                <Link href="/admin/bureaux"
                      class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                    ← Retour aux bureaux
                </Link>
            </div>
        </template>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-800">
                    Photos du bureau
                    <span class="text-gray-400 font-normal text-sm">({{ images.length }})</span>
                </h2>
            </div>

            <PhotoGallery :images="images" />
        </div>
    </AuthenticatedLayout>
</template>