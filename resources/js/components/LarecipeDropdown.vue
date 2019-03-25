<template>
  <div class="inline-flex relative" v-click-outside="handleClickOutside">
    <div @click="isOpen = !isOpen">
      <slot></slot>
    </div>
    <div v-show="isOpen" class="absolute z-20 pin-r mt-12 shadow-lg rounded bg-white overflow-hidden">
      <slot name="list"></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: "larecipe-dropdown",
  data() {
    return {
      isOpen: false
    };
  },
  methods: {
    handleClickOutside() {
      if (this.isOpen) {
        this.isOpen = false;
      }
    }
  }
};
</script>
