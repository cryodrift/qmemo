import {customEvent} from "/dataloader.js";

const store = {}

export function listen(elem, formid) {
   const originaldata = {}
   let id = ''
   const form = document.getElementById(formid)
   const newbtn = form.querySelector('button[value=create]')
   const updbtn = form.querySelector('button[value=update]')

   form.querySelectorAll('input, textarea, select').forEach(input => {
      originaldata[input.name] = input.value
      if (input.name === 'content') store.content = input
      switch (input.type) {
         case 'hidden':
            if (input.name == 'id') id = input.value
            break;
         default:
            input.removeEventListener('input', mainlistener(originaldata, id, newbtn, updbtn))
            input.addEventListener('input', mainlistener(originaldata, id, newbtn, updbtn))
      }
   })
   window.removeEventListener('memoinsertlisten.start', mainlistener(originaldata, id, newbtn, updbtn))
   window.addEventListener('memoinsertlisten.start', mainlistener(originaldata, id, newbtn, updbtn))
}

export function notify() {
   const event = new CustomEvent('memoinsertlisten.start', {
      'detail': {
         'name': 'content',
         'value': store.content?.value
      }, cancelable: false
   })
   return window.dispatchEvent(event)
}

function mainlistener(originaldata, id, newbtn, updbtn) {
   return (e) => {
      const target = e.detail || e.target
      if (originaldata[target.name] != target.value) {
         if (target.name == 'name') {
            if (id) {
               newbtn.classList.add('g-active')
            } else {
               updbtn.classList.add('g-active')
            }
         } else {
            updbtn.classList.add('g-active')
         }
         // console.log('observer.js', target.name, target.value, id)
      } else {
         newbtn.classList.remove('g-active')
         updbtn.classList.remove('g-active')
      }
   }
}

