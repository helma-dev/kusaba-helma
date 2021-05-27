var PainterCore = {
  init: function() {
    var cnt, btns;
    
    if (!document.forms.postform) {
      return;
    }
    
    cnt = document.forms.postform.getElementsByClassName('painter-ctrl')[0];
    
    if (!cnt) {
      return;
    }
    
    btns = cnt.getElementsByTagName('button');
    
    if (!btns[1]) {
      return;
    }
    
    this.data = null;
    
    this.time = 0;
    
    this.btnDraw = btns[0];
    this.btnClear = btns[1];
    this.btnFile = document.getElementsByName('imagefile')[0];
    this.btnSubmit = document.forms.postform.querySelector('input[type="submit"]');
    this.inputNodes = cnt.getElementsByTagName('input');
    
    btns[0].addEventListener('click', this.onDrawClick, false);
    btns[1].addEventListener('click', this.onCancel, false);
  },
  
  onDrawClick: function() {
    var w, h, dims = this.parentNode.getElementsByTagName('input');
    
    w = +dims[0].value;
    h = +dims[1].value;
    
    if (w < 1 || h < 1) {
      return;
    }
    
   
    Tegaki.open({
      onDone: PainterCore.onDone,
      onCancel: PainterCore.onCancel,
      saveReplay: false,
      width: w,
      height: h
    });
  },
  
  b64toBlob: function(data) {
    var i, bytes, ary, bary, len;
    
    bytes = atob(data);
    len = bytes.length;
    
    ary = new Array(len);
    
    for (i = 0; i < len; ++i) {
      ary[i] = bytes.charCodeAt(i);
    }
    
    bary = new Uint8Array(ary);
    
    return new Blob([bary]);
  },
  
  onDone: function() {
    var self, blob, el;
    
    self = PainterCore;
        
    self.btnFile.disabled = true;
    self.btnClear.disabled = false;
    
    self.data = Tegaki.flatten().toDataURL('image/png');
    
    if (!Tegaki.hasCustomCanvas && Tegaki.startTimeStamp) {
      self.time = Math.round((Date.now() - Tegaki.startTimeStamp) / 1000);
    }
    else {
      self.time = 0;
    }
    
    self.btnFile.style.visibility = 'hidden';
    self.btnDraw.textContent = 'Edit';
    
    for (el of self.inputNodes) {
      el.disabled = true;
    }
	
    document.forms.postform.addEventListener('submit', self.onSubmit, false);
  },
  
  onCancel: function() {
    var self = PainterCore;
    
    
    self.data = null;
    self.replayBlob = null;
    self.time = 0;
    
    self.btnFile.disabled = false;
    self.btnClear.disabled = true;
    
    self.btnFile.style.visibility = '';
    
    self.btnDraw.textContent = 'Paint!';
    
    for (el of self.inputNodes) {
      el.disabled = false;
    }
    
    document.forms.postform.removeEventListener('submit', self.onSubmit, false);
  },
  
  onSubmit: function(e) {
    var formdata, blob, xhr;
    
    e.preventDefault();
    
    formdata = new FormData(this);
    
    blob = PainterCore.b64toBlob(PainterCore.data.slice(PainterCore.data.indexOf(',') + 1));
    
    if (blob) {
      formdata.append('imagefile', blob, 'oekaki.png');
    }
    
    
    xhr = new XMLHttpRequest();
    xhr.open('POST', this.action, true);
    xhr.withCredentials = true;
    xhr.onerror = PainterCore.onSubmitError;
    xhr.onload = PainterCore.onSubmitDone;
    
    xhr.send(formdata);
    
    PainterCore.btnSubmit.disabled = true;
  },
  
  onSubmitError: function() {
    PainterCore.btnSubmit.disabled = false;
    showPostFormError('Connection Error.');
  },
  
  onSubmitDone: function() {
    var tid, board;
    PainterCore.btnSubmit.disabled = false;
    board = location.pathname.split(/\//)[1];
    tid = document.postform.replythread.value;
    if (tid == "0") {
      PainterCore.btnClear.disabled = true;
      window.location.reload();
    } else {
      window.location.href = '/' + board + '/res/' + tid + '.html';
    }
    return;
  }
};