export default class {

   /** @type {Ce} */
   #editor

   constructor() {
   }

   notify(editor) {
      this.#editor = editor
      //look if image is selected
      // attach handlers to the image
      //
   }
}

function oldcode() {
   // Touch Display
   heditor.on('mousedown', function (e) {
      if (e.target.localName === 'img') {
         e.preventDefault();
         $$(e.target).on('mouseout', function () {
            window.removeEventListener('mousemove', $$.mousemove);
         });
         $$.mousemove = mouseResize(e.target);
         window.addEventListener('mousemove', $$.mousemove);
      }
   });
   heditor.on('mouseup', function (e) {
      window.removeEventListener('mousemove', $$.mousemove);
      markChanged(heditor.html());
   });
   heditor.on('touchstart', function (e) {
      if (e.target.localName === 'img') {
         e.preventDefault();
         $$.mousemove = mouseResize(e.target);
         $$.mousemoveElement = e.target;
         $$.mousemoveElement.addEventListener('touchmove', $$.mousemove);
      }
   });
   heditor.on('touchend', function (e) {
      $$.mousemoveElement.removeEventListener('touchmove', $$.mousemove);
      markChanged(heditor.html());
   });


   // imageresize without jquery
   function mouseResize(elem) {
      var w = $$(elem).css('width', undefined, true);
      var h = $$(elem).css('height', undefined, true);
      var ofx = 0;
      var ofy = 0;
      return function (e) {
         e.preventDefault();
         var x = e.changedTouches ? e.changedTouches[0].clientX : e.offsetX;
         var y = e.changedTouches ? e.changedTouches[0].clientY : e.offsetY;
         if (x <= ofx && (y <= ofy || e.altKey)) w -= 5;
         else if (x >= ofx && (y >= ofy || e.altKey)) w += 5;
         if (y <= ofy && (x <= ofx || e.altKey)) h -= 5;
         else if (y >= ofy && (x >= ofx || e.altKey)) h += 5;
         if (e.ctrlKey) {
            $$(elem).css('width', w + 'px')
               .css('height', h + 'px');
         } else {
            $$(elem).css('width', w + 'px')
               .css('height', null);
         }
         // $$.log(w, h);
         ofx = x;
         ofy = y;
      }
   }

}
