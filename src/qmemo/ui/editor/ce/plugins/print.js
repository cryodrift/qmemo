import {logMissingParams} from "/ce.js";

export default class {

   /** @type {Ce} */
   #editor

   constructor(buttonId) {
      logMissingParams({buttonId})
      document.getElementById(buttonId).addEventListener('click', e => {
         this.printDoc()
      })
   }

   handleEditorchange(ce, args) {
      this.#editor = ce
   }

   printDoc() {
      let oPrntWin = window.open("", "_blank", "width=800,height=600,left=20,top=20,menubar=yes,toolbar=no,location=no,scrollbars=yes");
      oPrntWin.document.open();
      oPrntWin.document.write("<!doctype html><html><head><title>Print<\/title><\/head><body onload=\"print();\">" + this.#editor.getEditor() + "<\/body><\/html>");
      oPrntWin.document.close();
   }

}
