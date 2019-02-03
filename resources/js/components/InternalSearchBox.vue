<template>
    <div class="search-box" v-click-outside="close">
        <larecipe-input input-classes="internal-search-input has-text-centered" placeholder="Search"></larecipe-input>
        <div class="autocomplete-result">
            <ul>
                <li v-for="page in filteredPages" :key="page.path">
                    <span class="title">{{ page.title }}</span>
                    <hr>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
export default {
    name: "internal-search-box",
    props: ['version'],
    data() {
        return {
            input: '',
            pages: [],
        };
    },
    methods: {
        close() {
            this.$emit('close')
        }
    },
    computed: {
        filteredPages() {
            return this.pages
        }
    },
    mounted() {
        $('.internal-search-input').focus()
        
        axios.get('/docs/search-index/' + this.version)
            .then(res => {
                this.pages = res.data
            })
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
}

.autocomplete-result {
    width: 400px !important; 
    border-radius: 10px;
    margin-top: 16rem;
    padding-top: 20px;
    transition: all 0.2s; 
    background-color: #ffffff;
    height: 400px; 
    position: absolute; 
    right: 10px;
    z-index: 100;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    overflow: scroll;

    ul  {
        list-style: none;
        margin-left: -20px !important;

        li {
            background: #ffffff;
            width: 100%;

            hr {
                margin-top: 0.5rem;
            }
        }
    }
}
</style>
