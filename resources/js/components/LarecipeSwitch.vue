<template>
    <label class="custom-toggle">
        <input type="checkbox"
               checked="true"
               @change="$emit('toggle')">
        <span class="custom-toggle-slider rounded-circle"></span>
    </label>
</template>
<script>
export default {
  name: "larecipe-switch",
};
</script>
