require('./bootsrap');

import LaRecipe from './Larecipe';

;(function() {
  this.CreateLarecipe = function() {
      return new LaRecipe()
  }
}.call(window));