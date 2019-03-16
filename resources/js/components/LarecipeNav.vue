<template>
  <div></div>
</template>

<script>
export default {
  name: "larecipe-nav",
  computed: {
    hideLogoOnSmallScreens() {
      return this.$slots["left-side"];
    }
  }
};
</script>