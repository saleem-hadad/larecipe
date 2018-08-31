<template>
    <label class="custom-toggle">
        <input type="checkbox"
               @change="$emit('toggle')">
        <span class="custom-toggle-slider rounded-circle"></span>
    </label>
</template>
<script>
export default {
  name: "larecipe-switch",
};
</script>
