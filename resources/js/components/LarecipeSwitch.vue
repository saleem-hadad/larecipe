<template>
    <label class="custom-toggle">
        <input type="checkbox"
               v-model="model"
               v-bind="$attrs"
               v-on="$listeners">
        <span class="custom-toggle-slider rounded-circle"></span>
    </label>
</template>
<script>
export default {
  name: "larecipe-switch",
  props: {
    value: {
      default: false,
      description: "Switch value"
    }
  },
  computed: {
    model: {
      get() {
        return this.value;
      },
      set(value) {
        this.$emit("input", value);
      }
    }
  }
};
</script>
