<script setup>
const props = defineProps({
    count: { type: Number, default: 0 },
    name: { type: String, default: 'Option' }
})

const fives = Math.floor(props.count / 5)
const remainder = props.count % 5
</script>

<template>
    <div class="mb-6">
        <!-- En-tête avec Nom et Total -->
        <div class="flex justify-between items-end mb-2 border-b border-gray-200 pb-1">
            <span class="text-sm font-bold text-gray-800 truncate pr-2">{{ name }}</span>
            <span class="text-lg font-mono font-bold text-blue-700">{{ count }} voix</span>
        </div>
        
        <!-- Conteneur : 480px permet d'afficher exactement 10 éléments avant retour à la ligne -->
        <div class="flex flex-wrap gap-2 max-w-[480px]">
            
            <!-- Groupes de 5 : Carré fermé + Diagonale -->
            <svg 
                v-for="i in fives" 
                :key="'5-'+i" 
                viewBox="0 0 40 40" 
                class="w-10 h-10 flex-shrink-0 text-blue-600"
                title="5 voix"
            >
                <rect x="4" y="4" width="32" height="32" fill="rgba(59, 130, 246, 0.1)" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="4" y1="4" x2="36" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            
            <!-- Reste : UNE SEULE forme selon le nombre (1 à 4) -->
            <svg 
                v-if="remainder === 4"
                viewBox="0 0 40 40" 
                class="w-10 h-10 flex-shrink-0 text-blue-600"
                title="4 voix"
            >
                <rect x="4" y="4" width="32" height="32" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            
            <svg 
                v-if="remainder === 3"
                viewBox="0 0 40 40" 
                class="w-10 h-10 flex-shrink-0 text-blue-600"
                title="3 voix"
            >
                <!-- Gauche + Haut + Bas (carré ouvert à droite) -->
                <line x1="4" y1="4" x2="4" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <line x1="4" y1="4" x2="36" y2="4" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <line x1="4" y1="36" x2="36" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            
            <svg 
                v-if="remainder === 2"
                viewBox="0 0 40 40" 
                class="w-10 h-10 flex-shrink-0 text-blue-600"
                title="2 voix"
            >
                <!-- Gauche + Haut (forme en L) -->
                <line x1="4" y1="4" x2="4" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <line x1="4" y1="4" x2="36" y2="4" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            
            <svg 
                v-if="remainder === 1"
                viewBox="0 0 40 40" 
                class="w-10 h-10 flex-shrink-0 text-blue-600"
                title="1 voix"
            >
                <!-- Gauche uniquement -->
                <line x1="4" y1="4" x2="4" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            
        </div>
    </div>
</template>