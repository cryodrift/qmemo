import {logMissingParams} from '/ce.js'
import {wrapRange, removeTagsFrom, getTextNodes, getRangeHtml, getRangeContainer, createTextRange, createNodeRange, combineTextNodes, htmltotext} from '/ce-plugins/range.js'
/*
* ce editor plugin
* allows to edit the currently selected tag and parent
* */

export default class Tags {
   #editortextEl
   #editorhtmlEl
   #taglist
   #replacemode
   #destmode = ''
   #htmlmode
   #editor

   constructor(editortextId, editorhtmlId, taglistselector, modeselector) {
      logMissingParams({editortextId, editorhtmlId, taglistselector, modeselector})

      this.#editorhtmlEl = document.getElementById(editortextId)
      this.#editortextEl = document.getElementById(editorhtmlId)


      this.#taglist = document.querySelectorAll(taglistselector)
      for (let el of this.#taglist) {
         el.addEventListener('click', this.#handleTagChange.bind(this))
      }

      this.#replacemode = document.querySelectorAll(modeselector)
      for (let el of this.#replacemode) {
         el.addEventListener('change', this.#handleModeChange.bind(this))
      }

      this.#editorhtmlEl.addEventListener('input', (e) => {
         if (this.#editor) {
            if (this.#editor.isSelected()) {
               this.#editor.replaceNode(this.#editorhtmlEl.innerText)
               this.#changeTextField(this.#editor.getTextContent())
            }
         }
      })

      this.#editortextEl.addEventListener('input', (e) => {
         if (this.#editor) {
            if (this.#editor.isSelected()) {
               this.#editor.setTextContent(this.#editortextEl.innerText)
               if (this.#editor.isHtmlmode()) {
                  this.#changeHtmlField(this.#editor.getOuterHtml())
               } else {
                  this.#changeHtmlField()
               }
            }
         }
      })
   }

   handleEditorchange(ce, args) {
      this.#editor = ce
      const [eventtype, eventname, info] = args
      // console.log('tags', args)
      if (eventtype === 'end' && ['updateEditor'].includes(eventname)) {
         this.#changeTextField()
         this.#changeHtmlField()
      }
      if ((eventname === 'handleClick' && eventtype === 'beg') || (eventtype === 'end' && ['changeNodeName', 'changeParentNodeName', 'replaceNode'].includes(eventname))) {
         if (this.#editor.isSelected()) {
            this.#changeTextField(this.#editor.getTextContent())
            if (this.#editor.isHtmlmode()) {
               this.#changeHtmlField(this.#editor.getOuterHtml())
            } else {
               this.#changeHtmlField()
            }
         } else {
            this.#changeTextField()
            this.#changeHtmlField()
         }
      }
   }

   #changeTextField(text) {
      if (text) {
         if (text !== this.#editortextEl.innerText) this.#editortextEl.innerText = text
      } else
         this.#editortextEl.innerText = ''
   }

   #changeHtmlField(content) {
      if (content) {
         const val1 = htmltotext(this.#editorhtmlEl.innerText)
         const val2 = htmltotext(content)
         // console.log('tags-html', content, val1, val2)
         if (val1 !== val2) this.#editorhtmlEl.innerText = val2
      } else {
         this.#editorhtmlEl.innerText = ''
      }
   }

   #handleTagChange(e) {
      if (this.#editor) {
         switch (this.#destmode) {
            case 'outer':
               this.#editor.changeParentNodeName(e.target.value)
               break;
            default:
               this.#editor.changeNodeName(e.target.value)
               break;
         }
      }
   }

   #handleModeChange(e) {
      this.#destmode = e.target.checked ? 'outer' : ''
      this.#editor.externalChange('tags', 'mode', this.#destmode)
   }

   #saveCursor() {

   }

   #restoreCursor() {

   }
}
