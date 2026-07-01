<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref, onBeforeUnmount } from 'vue'

const props = defineProps({
    candidat: Object,
})

const form = useForm({
    nom: props.candidat.nom,
    ordre_affichage: props.candidat.ordre_affichage,
    photo: null,
    _method: 'put', // method spoofing indispensable pour l'upload de fichier
})

const currentPhotoUrl = props.candidat.photo ? `/storage/${props.candidat.photo}` : null
const photoPreview = ref(null)
const fileInput = ref(null)

const onImgError = (e) => {
    e.target.src = '/images/candidat-placeholder.png'
}

// --- Upload classique ---
const onFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    setPhoto(file)
}

const setPhoto = (file) => {
    form.photo = file
    photoPreview.value = URL.createObjectURL(file)
}

const removeNewPhoto = () => {
    form.photo = null
    photoPreview.value = null
    if (fileInput.value) fileInput.value.value = ''
}

// --- Webcam ---
const showCamera = ref(false)
const videoRef = ref(null)
const canvasRef = ref(null)
let mediaStream = null

const openCamera = async () => {
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        })
        showCamera.value = true
        setTimeout(() => {
            if (videoRef.value) videoRef.value.srcObject = mediaStream
        }, 0)
    } catch (err) {
        alert("Impossible d'accéder à la caméra. Vérifiez les permissions du navigateur.")
        console.error(err)
    }
}

const closeCamera = () => {
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop())
        mediaStream = null
    }
    showCamera.value = false
}

const capturePhoto = () => {
    const video = videoRef.value
    const canvas = canvasRef.value
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)

    canvas.toBlob((blob) => {
        const file = new File([blob], `candidat-${Date.now()}.jpg`, { type: 'image/jpeg' })
        setPhoto(file)
        closeCamera()
    }, 'image/jpeg', 0.9)
}

onBeforeUnmount(() => {
    if (mediaStream) mediaStream.getTracks().forEach((track) => track.stop())
})

const submit = () => {
    form.post(`/admin/candidats/${props.candidat.id}`, {
        forceFormData: true,
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Modifier {{ candidat.nom }}</h1>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-100 p-6 space-y-4">

                <!-- Photo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Photo du candidat</label>

                    <!-- Aperçu nouvelle photo sélectionnée -->
                    <div v-if="photoPreview" class="mb-3 relative w-40">
                        <img :src="photoPreview" alt="Nouvel aperçu"
                             class="w-40 h-40 object-cover rounded-lg border border-gray-200" />
                        <button type="button" @click="removeNewPhoto"
                                class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700">
                            ✕
                        </button>
                        <p class="text-xs text-gray-500 mt-1">Nouvelle photo (non enregistrée)</p>
                    </div>

                    <!-- Photo actuelle si pas de nouvelle sélection -->
                    <div v-else class="mb-3 w-40">
                        <img :src="currentPhotoUrl || '/images/candidat-placeholder.png'"
                             @error="onImgError"
                             alt="Photo actuelle"
                             class="w-40 h-40 object-cover rounded-lg border border-gray-200" />
                        <p class="text-xs text-gray-500 mt-1">
                            {{ currentPhotoUrl ? 'Photo actuelle' : 'Aucune photo' }}
                        </p>
                    </div>

                    <!-- Vue caméra -->
                    <div v-if="showCamera" class="mb-3 space-y-2">
                        <video ref="videoRef" autoplay playsinline
                               class="w-full max-w-sm rounded-lg border border-gray-300 bg-black"></video>
                        <canvas ref="canvasRef" class="hidden"></canvas>
                        <div class="flex gap-2">
                            <button type="button" @click="capturePhoto"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                Prendre la photo
                            </button>
                            <button type="button" @click="closeCamera"
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                                Annuler
                            </button>
                        </div>
                    </div>

                    <div v-if="!showCamera" class="flex flex-wrap gap-2">
                        <button type="button" @click="fileInput.click()"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                            📁 Choisir un fichier
                        </button>
                        <button type="button" @click="openCamera"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                            📷 Prendre une photo
                        </button>
                    </div>

                    <input ref="fileInput" type="file" accept="image/*" capture="environment"
                           class="hidden" @change="onFileChange" />

                    <p v-if="form.errors.photo" class="text-red-600 text-sm mt-1">{{ form.errors.photo }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom du candidat</label>
                    <input v-model="form.nom" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required />
                    <p v-if="form.errors.nom" class="text-red-600 text-sm mt-1">{{ form.errors.nom }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro (ordre d'affichage)</label>
                    <input v-model.number="form.ordre_affichage" type="number" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required />
                    <p v-if="form.errors.ordre_affichage" class="text-red-600 text-sm mt-1">{{ form.errors.ordre_affichage }}</p>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium disabled:opacity-50">
                        Mettre à jour
                    </button>
                    <Link :href="`/admin/candidats`"
                          class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                        Annuler
                    </Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>