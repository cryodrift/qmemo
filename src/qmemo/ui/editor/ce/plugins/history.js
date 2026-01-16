import {logMissingParams} from "/ce.js";

export default class {
   #history = []
   #hpointer = 0
   #btnprev
   #btnnext
   #lastpointer
   /** @type {Ce} */
   #editor

   constructor(prevId, nextId) {
      logMissingParams({prevId, nextId})

      if (prevId) document.getElementById(prevId).addEventListener('click', e => {
         if (this.#history.length) {
            this.#hpointer--
            this.#hpointer = Math.max(0, this.#hpointer)
            if (this.#lastpointer !== this.#hpointer) {
               this.#lastpointer = this.#hpointer
               this.#editor.updateEditor(this.#history[this.#hpointer])
            }
         }
      })

      if (nextId) document.getElementById(nextId).addEventListener('click', e => {
         if (this.#history.length) {
            this.#hpointer++
            this.#hpointer = Math.min(this.#history.length - 1, this.#hpointer)
            if (this.#lastpointer !== this.#hpointer) {
               this.#lastpointer = this.#hpointer
               this.#editor.updateEditor(this.#history[this.#hpointer])
            }
         }
      })
   }

   handleEditorchange(ce, args) {
      this.#editor = ce
      const [eventtype, eventname, nodename] = args
      // console.log('hist', args)
      if (eventtype === 'addPlugin') {
         this.#historyPush(this.#editor.getEditor())
      }
      if (eventtype === 'end' && (eventname === 'updateEditor' || eventname === 'updateContent')) {
         this.#historyPush(this.#editor.getEditor())
      }
   }

   #historyPush(value) {
      if (!this.#history.includes(value)) {
         this.#history.push(value)
         this.#hpointer = this.#history.length - 2
      }
   }
}
