import {logMissingParams} from '/ce.js'
import {toggleElement, createTextRange, getTextNodes, getRangeContainer, createNodeRange} from '/ce-plugins/range.js'


/*
* ce editor plugin
* allows to select words
* */

export default class Multi {

   /** @type [Range] */
   #savedRanges = [];

   /** @type {Ce} */
   #editor
   #selections
   #selparent
   #content
   #active = false

   constructor(editorId, selectionId, restoreId, hideId, switchId) {
      logMissingParams({editorId, selectionId, restoreId, hideId, switchId})
      this.#content = document.getElementById(editorId);

      this.#selparent = document.getElementById(selectionId);
      this.#selections = document.createElement('div')
      this.#selparent.append(this.#selections)


      if (switchId) document.getElementById(switchId).addEventListener('click', () => {
         if (this.#active) {
            this.#savedRanges = []
            this.#removehighlights()
            this.#active = false
            this.#selparent.classList.add('g-dn')
         } else {
            this.#active = true
            this.#selparent.classList.remove('g-dn')
         }
      })

      if (hideId) document.getElementById(hideId).addEventListener('click', () => {
         this.#removehighlights()
      })

      if (restoreId) document.getElementById(restoreId).addEventListener('click', e => {
         this.#highlightRanges()
      })

      this.#content.addEventListener('mouseup', this.#handleSelection.bind(this))
      // this.#content.addEventListener('keyup', this.#handleSelection.bind(this))
   }

   handleEditorchange(ce, args) {
      if (!this.#editor) {
         this.#editor = ce
         this.#editor.addScrollWatcher(e => {
            const element = e.target;
            const scrollTop = parseInt(element.scrollTop);
            const scrollHeight = parseInt(element.scrollHeight);
            const clientHeight = parseInt(element.clientHeight);
            const downscroll = scrollTop + clientHeight >= scrollHeight - 2
            this.#selparent.scrollTop = scrollTop
            // console.log(scrollTop, scrollHeight, clientHeight, downscroll)
         })
      }
      const dimpo = this.#editor.getEditorDimPo()
      this.#selparent.style.height = dimpo.h + 'px';
      this.#selections.style.height = dimpo.sh + 'px';
      this.#selparent.style.width = dimpo.w + 'px';
      this.#selparent.style.top = dimpo.t + 'px';
      this.#selparent.style.left = dimpo.l + 'px';

      if (this.#savedRanges.length) {
         const [eventtype, eventname, nodename] = args
         if (eventtype === 'beg' && eventname === 'changeNodeName') {
            console.log('multi notify changeNodeName', args)
            this.#removehighlights()
            let selection = window.getSelection()
            this.#savedRanges.forEach((range) => {
               selection.addRange(range)
               // console.log('multi range', nodename, range)
               toggleElement(range, nodename, this.#editor.isInsideEditor.bind(this.#editor))
            })
            this.#highlightRanges()
            this.#editor.updateContent()
            return true

         } else if (eventtype === 'beg' && eventname === 'changeParentNodeName') {
            // console.log('multi notify changeParentNodeName', args)
            const ranges = this.#createParentRanges()
            // console.log(ranges)
            this.#removehighlights()
            ranges.forEach(r => {
               const content = r.extractContents();
               const range = r.cloneRange()
               range.insertNode(content)
               const newnode = document.createElement(nodename)
               range.surroundContents(newnode)
               range.selectNode(newnode)
               this.#highlightRange(range)
            })
            return true
         } else if (eventtype === 'ext' && eventname === 'tags' && nodename === 'mode') {
            this.#removehighlights()
            this.#highlightRanges()
         }
      }
   }

   #highlightRange(range) {
      const containerRect = this.#content.getBoundingClientRect()
      const rects = range.getClientRects()
      // console.log('multi', range, rects)
      const rect = rects[0]
      if (rect) {
         const el = document.createElement('div')
         el.style.backgroundColor = 'transparent'
         el.style.color = 'transparent'
         el.style.border = '1px solid yellow'
         el.style.position = 'absolute'
         el.style.zIndex = '9999'
         el.style.pointerEvents = 'none'
         this.#selections.appendChild(el)
         const relativeTop = rect.top - containerRect.top + this.#selparent.scrollTop
         const relativeLeft = rect.left - containerRect.left + this.#selparent.scrollLeft
         // console.log('multi', rect, relativeLeft, relativeTop)
         el.style.left = relativeLeft + 'px'
         el.style.top = relativeTop + 'px'
         el.style.width = rect.width + 'px'
         el.style.height = rect.height + 'px'
      }
   }


   #removehighlights() {
      this.#selections.innerHTML = ''
      return
      document.querySelectorAll('.ce-selected-range').forEach(el => {
         const parent = el.parentNode
         while (el.firstChild) parent.insertBefore(el.firstChild, el)
         parent.removeChild(el)
      });
   }


   #highlightMultiline(range, selection) {
      const startEl = range.startContainer
      const stopEl = range.endContainer
      const startNr = range.startOffset
      const stopNr = range.endOffset
      const parent = getRangeContainer(range)
      let textnodes = getTextNodes(parent, startEl, stopEl)

      textnodes.forEach(textnode => {
         try {
            const range = createTextRange(textnode, startNr, stopNr)
            this.#savedRanges.push(range)
            this.#highlightRange(range)
         } catch (error) {
            console.log('multi', error)
         }
      })
   }

   #highlightRanges(ranges) {
      const selection = window.getSelection()
      selection.removeAllRanges()

      if (!ranges || ranges?.length < 1) ranges = this.#savedRanges

      if (ranges.forEach) ranges.forEach((range) => {
         selection.addRange(range)
         this.#highlightRange(range)
      })
   }

   #createParentRanges() {
      const ranges = []
      this.#savedRanges.forEach(r => {
         const container = getRangeContainer(r)
         ranges.push(createNodeRange(container))
      })
      return ranges
   }

   #handleSelection(e) {
      if (this.#active) {
         const selection = window.getSelection();
         const anchor = selection.anchorNode
         // selection.selectAllChildren(anchor.parentNode)
         // if (selection.rangeCount > 0 && selection.type.toLowerCase() === 'range') {
         if (selection.rangeCount > 0) {
            for (let a = 0; a < selection.rangeCount; a++) {
               const range = selection.getRangeAt(a);
               if (selection.anchorNode === selection.focusNode) {
                  if (!this.#savedRanges.includes(range)) {
                     const clonedrange = range.cloneRange()
                     this.#savedRanges.push(clonedrange);
                     this.#highlightRange(clonedrange)
                  }
               } else {
                  this.#highlightMultiline(range, selection)
               }
            }
            selection.removeAllRanges()
         }
      }
   }
}
