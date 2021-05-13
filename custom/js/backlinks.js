if (localStorage.getItem('backlinksEnabled') === null){
    localStorage.setItem('backlinksEnabled', 'true');
}

function updateBackLinks() {
    var i;
    var links = document.getElementsByTagName('a');
    var linkslen = links.length;
            for (i=0;i<linkslen;i++){
                    var linksclass = links[i].getAttribute('class');
                    var testref = links[i].parentNode.getAttribute('class');
                    if (linksclass != null && linksclass.indexOf('ref|') != -1 && (testref == undefined || testref != 'replybacklinks')) {
                            var onde = links[i].href.substr(links[i].href.indexOf('#') + 1);
                            var quem = links[i].parentNode.parentNode.parentNode.getElementsByTagName('a')[0].name;
                            var br = links[i].href.substring(0, links[i].href.indexOf('/res'));
br = br.substring(br.lastIndexOf('/')+1);
 
var tr = links[i].href.substring(links[i].href.lastIndexOf('/')+1, links[i].href.lastIndexOf('.'));
                            addBackLinks(quem, onde, tr, br);
				var replylinks = 'repback' + onde + br;
                    }
            }
       
        function addBackLinks (quem, onde, tr, br) {
            var ondeid = document.getElementById('reply' + onde);
            if (ondeid != undefined) {
                    var onderefl = ondeid.querySelectorAll('span.replybacklinks')[0];
                    if (onderefl.innerHTML.indexOf(quem) == -1){
                            var e = document.createElement('a');
                            e.innerHTML='&nbsp;<u>>>' + quem + '</u>';
                            e.setAttribute('href','/' + br + '/res/' + tr + '.html#' + quem);
                            e.setAttribute('class','ref|' + br + '|' + tr + '|' + quem);
                            e.setAttribute('onclick','return highlight(\'' + quem + '\', true);');
				onderefl.appendChild(e);
                            return linkslen++;
                    }
            }
        }
    return 0;
}

if (localStorage.getItem('backlinksEnabled') == 'true'){     
    updateBackLinks();
}
