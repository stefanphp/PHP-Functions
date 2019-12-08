var mainImg = document.getElementById('img-main');
var thumbCont = document.getElementById('img-thumb-wrapper');


async function getData(id)
{
    var res = await fetch('script/w.php?imgid='+id);
    res = await res.json();
    console.log(res);
}

async function getRange(start, offset)
{
    var res = await fetch('script/w.php?start='+start+'&offset='+offset);
    res = await res.json();
    res.forEach(el => {

        var div = document.createElement('div');
        div.classList.add('img-thumb');
        var div2 = document.createElement('img');
        div2.classList.add('img-data');
        div2.src = 'data:image/jpeg;base64,'+el.thumb;
        div.appendChild(div2);
        thumbCont.appendChild(div);
    });
}

for(var i = 1; i<=40; i+=5)
    getRange(i, 5);

