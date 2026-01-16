import {dom} from '/system.js'
import {getRangeContainer, isSelection, toggleElement, getRange, wrapRange} from '/ce-plugins/range.js'


/** @type {Ce} */
export default class Ce {
   #dTarget
   #sElem
   #htmlmode
   #plugins = []
   #contentEl
   #editorEl
   #modeselectorEl
   #infoboxEl

   constructor(contentId, editorId, infoboxId) {
      logMissingParams({contentId, editorId, infoboxId})
      this.#contentEl = document.getElementById(contentId)
      this.#contentEl.addEventListener('input', e => {
         this.updateEditor(e.target.value)
      })

      this.#editorEl = document.getElementById(editorId)
      this.#editorEl.addEventListener('click', this.#handleClick.bind(this))
      this.#editorEl.addEventListener('keyup', this.#handleClick.bind(this))
      this.#editorEl.addEventListener('input', this.updateContent.bind(this))
      this.#infoboxEl = document.getElementById(infoboxId)
      this.#editorEl.innerHTML = this.#contentEl.value
   }

   #changeNodeName(newNodeName, node) {
      if (node) {
         if (this.#htmlmode) {
            if (newNodeName) {
               // console.log('ce change ', newNodeName)
               const newNode = this.#newNode(newNodeName, node)
               node.parentNode.replaceChild(newNode, node);
               return newNode
            } else {
               const parent = node.parentElement
               // console.log('ce remove ')
               const newNode = document.createTextNode(node.innerHTML)
               while (node.firstChild) parent.insertBefore(node.firstChild, node)
               parent.removeChild(node)
               if (this.isInsideEditor(parent)) {
                  return parent
               } else
                  return null
            }
         } else {
            // TODO textmode
         }
      }

   }

   changeNodeName(newNodeName) {
      if (this.#notifyPlugins('beg', 'changeNodeName', newNodeName)) {
         if (isSelection()) {
            const range = getRange()
            // console.log('ce range mode', range)
            toggleElement(range, newNodeName, this.isInsideEditor.bind(this))
         } else {
            this.#sElem = this.#changeNodeName(newNodeName, this.#sElem)
            this.updateContent()
            this.#notifyPlugins('end', 'changeNodeName', newNodeName)

         }
      }
   }

   changeParentNodeName(newNodeName) {
      if (this.#notifyPlugins('beg', 'changeParentNodeName', newNodeName)) {
         if (isSelection()) {
            const range = getRange()
            // console.log('ce range mode', range)
            const parent = getRangeContainer(range)
            if (this.isInsideEditor(parent)) {
               range.selectNode(parent)
               toggleElement(range, newNodeName, this.isInsideEditor.bind(this))
            }
         } else {
            if (this.#sElem) {
               if (this.#htmlmode) {
                  const parent = this.#sElem.parentNode
                  if (newNodeName) {
                     console.log('ce parent mode', newNodeName)
                     if (this.isInsideEditor(parent)) {
                        this.#changeNodeName(newNodeName, parent)
                        // console.log('ce parent mode inside', this.#sElem)
                     } else {
                        // console.log('ce parent mode outside', parent)
                        const newNode = document.createElement(newNodeName);
                        const childNode = this.#newNode(this.#sElem.nodeName, this.#sElem)
                        newNode.appendChild(childNode)
                        this.#sElem.replaceWith(newNode)
                     }
                  } else {
                     const parent = this.#sElem.parentNode
                     if (this.isInsideEditor(parent)) {
                        parent.parentElement.insertBefore(this.#sElem, parent)
                        parent.parentElement.removeChild(parent)
                     }
                  }
               } else {
                  if (newNodeName) {
                     const newNode = document.createElement(newNodeName);
                     newNode.innerText = this.#sElem.nodeValue
                     this.#sElem.replaceWith(newNode)
                  } else {
                     const parent = this.#sElem.parentNode
                     if (this.isInsideEditor(parent)) {
                        parent.parentElement.replaceWith(this.#sElem)
                     }
                  }
               }
               this.updateContent()
               this.#notifyPlugins('end', 'changeParentNodeName', newNodeName)
            } else
               console.log('ce no elem in selection')
         }
      }
   }

   #handleClick(e) {
      e.preventDefault()
      const selection = window.getSelection()
      this.#sElem = null
      if (this.#isEditorSelection(selection)) {
         if (selection.anchorNode.parentElement === this.#editorEl) {
            this.#htmlmode = false;
            this.#sElem = selection.anchorNode;
         } else {
            this.#sElem = selection.anchorNode.parentElement;
            this.#htmlmode = true;
         }
      }
      this.#notifyPlugins('beg', 'handleClick')
   }

   #isEditorSelection(selection) {
      if (selection.anchorNode) {
         // console.log('isEditorSelection')
         if (this.#isTextSelection(selection)) {
            return true
         } else {
            return this.isInsideEditor(selection.anchorNode)
         }
      }
      return false
   }

   #isTextSelection(selection) {
      // console.log('isTextSelection', selection.anchorNode.nodeType, selection.anchorNode.parentElement === this.#editorEl)
      return selection.anchorNode.nodeType === 3 && selection.anchorNode.parentElement === this.#editorEl
   }

   isInsideEditor(node) {
      while (node && this.#editorEl !== node && node !== document.body) {
         // console.log('isInsideEditor', node)
         if (this.#editorEl.contains(node)) {
            // console.log('isInsideEditor',node)
            return true
         }
         node = node.parentNode
      }
      return false
   }

   isHtmlmode() {
      return this.#htmlmode;
   }

   setTextContent(text) {
      if (this.#sElem) {
         if (this.#notifyPlugins('beg', 'setTextContent', text)) {
            this.#sElem.textContent = text
            this.updateContent();
            this.#notifyPlugins('end', 'setTextContent', text)
         }
      }
   }

   getTextContent() {
      if (this.#sElem) return this.#sElem.textContent
   }

   getOuterHtml() {
      if (this.#sElem) return this.#sElem.outerHTML
   }

   /**
    *
    * @returns {Node}
    */
   getClone() {
      if (this.#sElem) return this.#sElem.cloneNode(true)
   }

   getNodeName() {
      if (this.#sElem) return this.#sElem.nodeName.toLowerCase()
   }

   getEditor() {
      return this.#editorEl.innerHTML
   }

   getContent() {
      return this.#contentEl.value
   }

   getNodeNameParent() {
      if (this.#sElem) return this.#sElem.parentNode.nodeName.toLowerCase()
   }

   isSelected() {
      return this.#sElem !== null
   }

   #newNode(newNodeName, node) {
      const newNode = document.createElement(newNodeName);
      for (let i = 0; i < node.attributes.length; i++) {
         const attr = node.attributes[i];
         newNode.setAttribute(attr.name, attr.value);
      }
      while (node.firstChild) {
         newNode.appendChild(node.firstChild);
      }
      return newNode
   }

   replaceNode(htmltext) {
      if (this.#sElem && this.getNodeName() !== '#text') {
         try {
            if (this.#notifyPlugins('beg', 'replaceNode', htmltext, this.#sElem.nodeName)) {
               const parent = this.#sElem.parentNode
               if (parent.nodeName.toLowerCase() !== '#text') {
                  const newElem = dom(htmltext.trim())
                  let newNode = newElem.firstChild
                  // console.log('ce', newNode.nodeType)

                  while (newElem.firstChild && newNode.nodeType !== 1) {
                     newElem.removeChild(newNode)
                     newNode = newElem.firstChild
                  }
                  this.#sElem.parentNode.replaceChild(newNode, this.#sElem)
                  this.#sElem = newNode
                  this.updateContent()
                  this.#notifyPlugins('end', 'replaceNode', this.getOuterHtml())
               }
            }
         } catch (error) {
            // console.log('replaceNode-error', error)
         }
      }
      return this.#sElem
   }

   #updateInfo() {
      if (this.#sElem) {
         this.#infoboxEl.innerText = this.getNodeName()
      }
   }

   updateContent() {
      if (this.#notifyPlugins('beg', 'updateContent')) {
         this.#contentEl.value = this.#editorEl.innerHTML;
         this.#notifyPlugins('end', 'updateContent')
      }
   }

   #notifyPlugins(...args) {
      let out = true
      for (let i in this.#plugins) {
         const plugin = this.#plugins[i]
         const res = plugin.handleEditorchange(this, args)
         if (res === true) out = false
      }
      this.#updateInfo()
      return out
   }

   updateEditor(value) {
      if (this.#notifyPlugins('beg', 'updateEditor', value)) {
         this.#editorEl.innerHTML = value;
         this.#sElem = null
         this.#notifyPlugins('end', 'updateEditor', value)
      }
   }

   /**
    * called from plugins
    * @param args
    */
   externalChange(...args) {
      args.unshift('ext')
      this.#notifyPlugins(...args)
   }

   addPlugin(plugin) {
      this.#plugins.push(plugin)
      plugin.handleEditorchange(this, ['addPlugin'])
   }

   getEditorDimPo(){
      return {
         h:this.#editorEl.offsetHeight,
         w:this.#editorEl.offsetWidth,
         t:this.#editorEl.offsetTop,
         l:this.#editorEl.offsetLeft,
         sh:this.#editorEl.scrollHeight,
      }
   }

   addScrollWatcher(cb){
      this.#editorEl.addEventListener('scroll', cb);
   }
}

export function logMissingParams(params) {
   // Iterate over the parameters and check for missing values
   for (const [key, value] of Object.entries(params)) {
      if (value == null || value === '') {
         // console.log(`Parameter missing: ${key}`);
      }
   }
}





