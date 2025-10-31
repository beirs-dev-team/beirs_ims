<script setup lang="ts">
import {ref} from 'vue'
const message = ref('')
</script>
<template>
    <h1>My Status</h1>

    <input v-model="message" placeholder="Incident Type"/>
    <input v-model="message" placeholder="Date Range"/>
    <input v-model="message" placeholder="Search"/>

    <div class="container mt-5">
        <table class="table table-bordered table-striped text-center">
            <thead class="thead-dark">
                <tr>
                    <th>CASE ID</th>
                    <th>INCIDENT TYPE</th>
                    <th>PERSON INVOLVED</th>
                    <th>DATE REPORTED</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="incident in incidents" :key ="incident.caseID">
                    <td>{{ incident.caseID }}</td>
                    <td>{{ incident.incidentType }}</td>
                    <td>{{ incident.personsInvolved }}</td>
                    <td>{{ incident.dateReported }}</td>
                    <td>
                        
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

    <button @click="">View</button>
    <button @click="">Generate PDF</button>
    <button @click="">Generate Excel</button>

    


</template>