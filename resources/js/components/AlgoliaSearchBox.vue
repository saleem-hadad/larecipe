<template>
    <div class="search-box" v-click-outside="close">
        <larecipe-input input-classes="algolia-search-input has-text-centered" placeholder="Search"></larecipe-input>
    </div>
</template>

<script>
require("docsearch.js/dist/cdn/docsearch.min.css")
import docsearch from 'docsearch.js/dist/cdn/docsearch.min.js'

export default {
    name: "algolia-search-box",
    props: ['algoliaKey', 'algoliaIndex', 'version'],
    methods: {
        close() {
            this.$emit('close')
        }
    },
    mounted() {
        docsearch({
            apiKey: this.algoliaKey,
            indexName: this.algoliaIndex,
            inputSelector: '.algolia-search-input',
            algoliaOptions: { 'facetFilters': ["version:" + this.version] },
            debug: false
        })

        $('.algolia-search-input').focus()
    }
}
</script>

<style lang="scss">
#search-button {
  color: gray;
  &.btn-primary {
      color: #ffffff;
  }
}

.search-box {
    width: 100% !important; 
    margin-top: 4rem; 
    transition: all 0.2s; 
    height: 6rem; 
    display: flex; 
    align-items: center; 
    position: absolute; 
    z-index: 100;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;

    .form-control {
        font-size: 2rem;
        text-transform: uppercase;
    }
    .form-control, .form-group {
        border: none;
        margin-bottom: 0px;
        height: 100%;
        text-align: center;
        width: 100%;
        background: #f4f5f7;
        border-radius: 0px;
        transition: all 0.2s;
        &:focus {
            background: #ffffff;
        }
      }
      .algolia-autocomplete {
        width: 100%;
        height: 100%;
        box-shadow: 0 1px 3px rgba(50,50,93,.15), 0 1px 0 rgba(0,0,0,.02);
      }
}
</style>
