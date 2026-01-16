import {logMissingParams} from '/ce.js'
import {dom} from '/system.js'


export default class {
   /**
    * @type {Ce}
    */
   #editor
   #oldvalues = {class: ''}
   #inputs
   #typeAttribute

   constructor(inputsSelector, typeAttribute) {
      logMissingParams({inputsSelector, typeAttribute})
      this.#typeAttribute = typeAttribute
      this.#inputs = document.querySelectorAll(inputsSelector)
      this.#inputs.forEach(el => {
         const type = el.getAttribute(typeAttribute)
         el.addEventListener('input', e => {
            if (this.#editor) {
               if (this.#editor.isSelected()) {
                  const node = this.#editor.getClone()
                  const value = e.target.value
                  if (value)
                     node.setAttribute(type, value)
                  else
                     node.removeAttribute(type)
                  // console.log('attr', node)
                  this.#editor.replaceNode(node.outerHTML)
               }
            }
         })
      })
   }

   handleEditorchange(ce, args) {
      this.#editor = ce
      const [eventtype, eventname, nodename] = args
      // console.log('attr', args)
      if (eventtype === 'beg' && eventname === 'handleClick') {
         this.#inputs.forEach(el => {
            const type = el.getAttribute(this.#typeAttribute)
            if (this.#editor) {
               if (this.#editor.isSelected()) {
                  const node = this.#editor.getClone()
                  if (node && node.nodeType === 1 && el.value !== node.getAttribute(type)) el.value = node.getAttribute(type)
               }
            }
         })
      }
   }
}
