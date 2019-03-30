<template>
  <div class="bg-grey-light h-2 rounded overflow-hidden my-4">
    <div class="h-full" :class="computedClasses" :style="`width: ${value}%;`"></div>
  </div>
</template>

<script>
export default {
  name: "larecipe-progress",
  props: {
    type: {
      type: String,
      default: "success",
      description: "Progress type (e.g danger, primary etc)"
    },
    value: {
      type: Number,
      default: 0,
      validator: value => {
        return value >= 0 && value <= 100;
      },
      description: "Progress value"
    }
  },
  computed: {
    computedClasses() {
      return [{ [`bg-${this.type}`]: this.type }];
    }
  }
};
</script>
