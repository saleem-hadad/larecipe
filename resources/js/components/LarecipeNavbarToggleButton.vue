<template>
    <button class="navbar-toggler btn-primary" type="button"
            data-toggle="collapse"
            :data-target="target"
            :aria-controls="target"
            :aria-expanded="toggled"
            aria-label="Toggle navigation">
        <i class="fa fa-bars navbar-toggler-icon"></i>
    </button>
</template>
<script>
export default {
  name: "larecipe-navbar-toggle-button",
  props: {
    target: {
      type: [String, Number],
      description: "Button target element"
    },
    toggled: {
      type: Boolean,
      default: false,
      description: "Whether button is toggled"
    }
  }
};
</script>
