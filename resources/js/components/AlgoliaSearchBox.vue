<template>
  <div class="search-box fixed pin-t border-t" v-click-outside="close">
    <input
        placeholder="Search..."
        class="form-control outline-none algolia-search-input text-center"/>
  </div>
</template>

<script>
require("docsearch.js/dist/cdn/docsearch.min.css");
import docsearch from "docsearch.js/dist/cdn/docsearch.min.js";

export default {
  name: "algolia-search-box",
  props: ["algoliaKey", "algoliaIndex", "version"],
  methods: {
    close(e) {
      let targetId = e.target.id;
      if(! ['search-button', 'search-button-icon'].includes(targetId)) {
        this.$emit("close");
      }
    },
  },
  mounted() {
    docsearch({
      apiKey: this.algoliaKey,
      indexName: this.algoliaIndex,
      inputSelector: ".algolia-search-input",
      algoliaOptions: { facetFilters: ["version:" + this.version] },
      debug: false
    });

    $(".algolia-search-input").focus();
  }
};
</script>