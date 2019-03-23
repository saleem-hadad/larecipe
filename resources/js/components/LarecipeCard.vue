<template>
    <div class="card" :class="classes">
        <div>
            <slot></slot>
        </div>
    </div>
</template>
<script>
export default {
  name: "larecipe-card",
  props: {
    type: {
      type: String,
      default: "default",
      description: "Card type"
    },
    shadow: {
      type: Boolean,
      description: "Whether card has shadow"
    },
    shadowSize: {
      type: String,
      description: "Card shadow size"
    },
  },
  computed: {
    classes() {
      return [
        {'shadow': this.shadow},
        {[`shadow-${this.shadowSize}`]: this.shadowSize},
        `is-${this.type}`
      ];
    }
  }
};
</script>
