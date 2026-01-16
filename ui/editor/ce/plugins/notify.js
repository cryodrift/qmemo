import {logMissingParams} from '/ce.js'
import {notify} from '/memoinsertobserver.js'

/*
* ce editor plugin
* notify other ui elements
* */

export default class Notify {

   constructor() {
   }

   handleEditorchange(ce, args) {
      const [eventtype, eventname] = args
      if (eventtype === 'end') {
         notify()
      }
   }


}
