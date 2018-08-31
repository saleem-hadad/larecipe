<template>
    <div id="backtotop"><a href="#"></a></div>
</template>

<script>
    export default {
        name: 'larecipe-back-to-top',
        mounted() {
            $(window).on('scroll', function() {
                if ($(window).scrollTop() >= 300) {
                    $("#backtotop").addClass('visible');
                } else {
                    $("#backtotop").removeClass('visible');
                }
            });
            $('#backtotop a').on('click', function() {
                $('html, body').animate({
                    scrollTop: 0
                }, 500);
                return false;
            });
        }
    }
</script>

<style>
    #backtotop {
        position: fixed;
        right: 0;
        opacity: 0;
        visibility: hidden;
        bottom: 25px;
        margin: 0 25px 0 0;
        z-index: 9999;
        transition: 0.35s;
        transform: scale(0.7);
        transition: all 0.5s;
    }
    
    #backtotop.visible {
        opacity: 1;
        visibility: visible;
        transform: scale(1);
    }
    
    #backtotop.visible a:hover {
        outline: none;
        opacity: 0.9;
        background: #34495e;
    }
    
    #backtotop a {
        outline: none;
        text-decoration: none;
        border: 0 none;
        display: block;
        width: 46px;
        height: 46px;
        background-color: #95a5a6;
        opacity: 1;
        transition: all 0.3s;
        border-radius: 50%;
        text-align: center;
        font-size: 26px
    }
    
    body #backtotop a {
        outline: none;
        color: #fff;
    }
    
    #backtotop a:after {
        outline: none;
        content: "\f106";
        font-family: "FontAwesome";
        position: relative;
        display: block;
        top: 50%;
        -webkit-transform: translateY(-55%);
        transform: translateY(-55%);
    }
</style>