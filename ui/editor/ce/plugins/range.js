export default class {
   constructor() {

   }
}

export function getRange() {
   const selection = window.getSelection()
   const range = selection.getRangeAt(0);
   return range
}

export function isSelection() {
   const selection = window.getSelection()
   return selection.type.toLowerCase() === 'range'
}

export function isCursor() {
   const selection = window.getSelection()
   return selection.type.toLowerCase() === 'caret'
}

export function createTextRange(node, startpos, maxlen) {
   const range = document.createRange()
   if (node.nodeType !== Node.TEXT_NODE) return range
   // range.setStart(node, 0)
   // range.setEnd(node, node.nodeValue.length)
   range.setStart(node, startpos)
   range.setEnd(node, Math.min(node.nodeValue.length, maxlen))
   return range
}

export function createNodeRange(node) {
   const range = document.createRange()
   range.selectNode(node)
   return range
}

export function combineTextNodes(parentNode) {
   let currentNode = parentNode.firstChild;

   while (currentNode) {
      if (currentNode.nodeType === Node.TEXT_NODE) {
         let nextNode = currentNode.nextSibling;

         // Merge consecutive text nodes
         while (nextNode && nextNode.nodeType === Node.TEXT_NODE) {
            currentNode.nodeValue += nextNode.nodeValue;
            parentNode.removeChild(nextNode);
            nextNode = currentNode.nextSibling;
         }
      }
      currentNode = currentNode.nextSibling;
   }
}

export function getTextNodes(node, begnode, endnode) {
   let textnodes = []
   let beg = false
   let end = true
   const treeWalker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, null)
   while (treeWalker.nextNode()) {
      const tnode = treeWalker.currentNode
      if (tnode === begnode) beg = true
      if (beg && end) {
         const parent = tnode.parentNode
         combineTextNodes(parent)
         textnodes.push(parent.firstChild)
      }
      if (tnode === endnode) end = false
   }
   return textnodes
}

export function getRangeHtml(range) {
   const container = document.createElement('div')
   container.appendChild(range.cloneContents())
   return container.innerHTML
}

export function wrapRange(range, wrapper) {
   const content = range.extractContents();
   wrapper.appendChild(content)
   range.insertNode(wrapper)
   range.selectNodeContents(wrapper)
   const selection = window.getSelection()
   selection.removeAllRanges()
   selection.addRange(range)
   return wrapper
}

export function toggleElement(range, nodename, inContainer) {

   if (nodename) {
      const parent = getRangeContainer(range)
      if (inContainer(parent) && parent.nodeName.toLowerCase() === nodename) {
         range.selectNode(parent)
         removeTagsFrom(range)
      } else {
         wrapRange(range, document.createElement(nodename))
      }
   } else {
      const parent = getRangeContainer(range)
      if (inContainer(parent)) {
         range.selectNode(parent)
      }
      removeTagsFrom(range)
   }
}

export function removeTagsFrom(range) {
   const content = range.extractContents();
   let text = ''
   const treeWalker = document.createTreeWalker(content, NodeFilter.SHOW_TEXT, null)
   while (treeWalker.nextNode()) {
      text += treeWalker.currentNode.nodeValue
   }
   range.insertNode(document.createTextNode(text))
}

export function getRangeContainer(range) {
   const commonAncestor = range.commonAncestorContainer
   const parent = commonAncestor.nodeType === Node.TEXT_NODE ? commonAncestor.parentNode : commonAncestor
   return parent
}

export function htmltotext(html) {
   const el = document.createElement('textarea')
   el.innerHTML = html
   return el.value
}

/**
 * Determines if the provided nodeName corresponds to a block-level element by checking its computed display property.
 * @param {string} nodeName - The name of the node to check (e.g., 'DIV', 'SPAN').
 * @returns {boolean} True if the nodeName corresponds to a block-level element, false otherwise.
 */
export function isNodeBlockElement(nodeName) {
   // Create a detached iframe
   const iframe = document.createElement('iframe');
   iframe.style.display = 'none';
   document.body.appendChild(iframe);

   // Get the iframe's document
   const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

   // Create the element within the iframe's document
   const tempElement = iframeDoc.createElement(nodeName);
   iframeDoc.body.appendChild(tempElement);

   // Get the computed style from the iframe's context
   const display = iframeDoc.defaultView.getComputedStyle(tempElement).display;

   // Clean up by removing the iframe
   document.body.removeChild(iframe);

   // Determine if the element is block-level
   return display === 'block';
}
