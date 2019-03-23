<template>
    <component :is="tag" @click="handleClick" class="button" :class="classes">
        <slot></slot>
    </component>
</template>

<script>
export default {
  name: "larecipe-button",
  props: {
    tag: {
      type: String,
      default: "button",
      description: "Button tag (default -> button)"
    },
    type: {
      type: String,
      default: "white",
      description: "Button type (e,g primary, danger etc)"
    },
    textColor: {
      type: String,
      default: "",
      description: "Button text color (e.g primary, danger etc)"
    },
    radius: {
      type: String,
      default: "md",
      description: "Border radius size"
    },
    size: {
      type: String,
      default: "base",
      description: "Border radius size"
    },
    block: {
      type: Boolean,
      default: false,
      description: "Whether button is of block type"
    }
  },
  computed: {
    classes() {
      let btnClasses = [
        { "w-full": this.block },
        { [`text-${this.textColor}`]: this.textColor },
        `is-${this.type}`,
        `rounded-${this.radius}`,
        `text-${this.size}`,
      ];
      
      return btnClasses;
    }
  },
  methods: {
    handleClick(evt) {
      this.$emit("click", evt);
    }
  }
};
</script>
