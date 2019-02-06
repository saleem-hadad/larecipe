<template>
    <div class="search-box" v-click-outside="close">
        <larecipe-input @input="filterResults" :value="search" input-classes="internal-search-input has-text-centered" placeholder="Search"></larecipe-input>

        <div class="autocomplete-result">
            <ul v-if="filteredPages.length">
                <li v-for="page in filteredPages" :key="page.path">
                    <a :href="'/docs/' + version + page.path"><span class="title">
                        <b>
                            {{ page.title }}
                        </b>
                    </span></a>

                    <hr>
                    
                    <p class="heading"  
                        v-for="heading in page.headings" 
                        :key="heading"
                        @click="navigateToHeading(page, heading)"
                    >{{ heading }}</p>
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
            search: '',
            pages: [],
        };
    },
    methods: {
        close() {
            this.$emit('close')
        },
        filterResults(value) {
            this.search = value
        },
        navigateToHeading(page, heading) {
            window.location = '/docs/' + this.version + page.path + '#' + this.slugify(heading)
        },
        slugify(heading) {
            return heading.toString().toLowerCase().replace(/\s+/g, '-')
        }
    },
    computed: {
        filteredPages() {
            return this.pages.filter(page => {
                let foundInHeading = false;

                page.headings.forEach(heading => {
                    if(heading.toLowerCase().includes(this.search)){
                        foundInHeading = true;
                    }
                });

                return page.title.toLowerCase().includes(this.search) || foundInHeading;
            })
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
    background-color: #ffffff;
    box-shadow: 0 0.125rem 1rem rgba(0, 0, 0, 0.075) !important;
    width: 400px !important; 
    max-height: 400px; 
    position: absolute; 
    top: 7rem;
    right: 10px;
    border-radius: 10px;
    transition: all 0.2s; 
    z-index: 100;
    overflow: scroll;

    ul  {
        list-style: none;
        margin-left: -20px !important;
        margin-right: 20px !important;

        li {
            background: #ffffff;
            width: 100%;
            margin-top: 20px;

            hr {
                margin-top: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .heading {
                width: 100%;
                padding: 5px 10px;
                cursor: pointer;
                margin-bottom: 0px;

                &:hover {
                    background: #f4f5f7;
                }
            }
        }
    }
}
</style>
