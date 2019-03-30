<template>
  <component :is="tag" class="badge inline-flex" :class="classes">
    <slot>
      <i v-if="icon" :class="icon"></i>
    </slot>
  </component>
</template>

<script>
export default {
  name: "larecipe-badge",
  props: {
    tag: {
      type: String,
      default: "span",
      description: "Html tag to use for the badge."
    },
    rounded: {
      type: Boolean,
      default: false,
      description: "Whether badge is of pill type"
    },
    circle: {
      type: Boolean,
      default: false,
      description: "Whether badge is circle"
    },
    icon: {
      type: String,
      default: "",
      description: "Icon name. Will be overwritten by slot if slot is used"
    },
    type: {
      type: String,
      default: "primary",
      description: "Badge type (info|danger|warning|success)"
    }
  },
  computed: {
    classes() {
      return [
        `is-${this.type}`,
        this.rounded && 'rounded',
        this.circle && 'rounded-full h-8 w-8 flex items-center justify-center'
      ];
    }
  }
};
</script>
