<template>
    <div class="card"
         :class="[
         {'shadow': shadow},
         {[`shadow-${shadowSize}`]: shadowSize},
         {[`is-${type}`]: type}
       ]">
        <div v-if="!noBody">
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
  }
};
</script>
