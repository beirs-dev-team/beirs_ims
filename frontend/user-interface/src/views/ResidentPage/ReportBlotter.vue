<script setup lang="ts">
function submit(/*event*/){

}

function clear(){

}
</script>
<template>
    <h1>Blotter Report</h1>
    <div class="left">
    <h2>Reporting Person</h2>

    <form action="/">
        <label for="date">Date</label>
 
        <label for="time">Time</label>

        <label for="fullName">Full Name</label>
        <input type="text" id="fullName" name="fullName"></input>

        <label for="age">Age</label>
        <input type="text" id="age" name="age"></input>

        <label for="gender">Gender</label>
        <input type="text" id="gender" name="gender"></input>

        <label for="address">Address</label>
        <input type="text" id="address" name="address"></input>

        <label for="contactNumber">Contact Number</label>
        <input type="number" id="contactNumber" name="contactNumber"></input>

        <input type="button" id="add" name="add">Add Entry</input>

    </form>
    </div>

    <div class="right">

    <h2>incident Report Details</h2>

    <form action="/">
        <label for="incidentType"></label>

        <label for="personsInvolved">Persons Involved</label>

        <label for="Location">Location</label>

        <label for="Witness">Witness</label>
        
        <label for="incidentDescription">Incident Description</label>
        <input type="text" id="description" name="description"></input>

        <input type="button" id="upload" name="upload">

        <input type="checkbox" id="check" name="check">I consent the collection and use of my data is for legal purposes.</input>
        <input type="checkbox" id="check" name="check">I Affirm that the information provide is accurate and complete.</input>
    </form>
    </div>

    <button @click="submit">Submit</button>
    <button @click="clear">Clear Form</button>

</template>

<style scoped>

</style>

