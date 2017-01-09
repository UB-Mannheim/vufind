var fulltextMappings = [
    {
        selector: 'a.fulltext[href*="nbn-resolving"]',
        text: 'Publication @ DNB',
        img: 'http://www.dnb.de/SiteGlobals/StyleBundles/Bilder/favicon.png?__blob=normal&v=1',
    },
    {
        selector: 'a.fulltext[href*="ub-madoc"]:not([href$=pdf])',
        text: 'Publication @ UB-Mannheim',
        img: "http://www.bib.uni-mannheim.de/fileadmin/favicon.ico",
    },
    {
        selector: 'a.fulltext[href*="madata"]',
        text: 'Raw data',
        img: 'https://upload.wikimedia.org/wikipedia/commons/5/54/Farm-Fresh_database_black.png',
    },
    {
        selector: 'a.fulltext[href*="ssoar.info"]',
        text: 'Publication @ SSOAR',
        img: 'http://www.ssoar.info/favicon.ico'
    },
];

var hideSelectors = [
    // Visual View
    () => $('a[href*="view=visual"]'),
    // Save Record
    () => $('a#save-record').parent('li'),
    // Send via SMS / Mail
    () => $('a#sms-record').parent('li'),
    () => $('a#mail-record').parent('li'),
    // Cite this Record
    () => $('a#cite-record').parent('li'),
];


$(document).ready(function(){
    hideSelectors.forEach(sel => sel().hide());
    $(".nav-pills").append(`
        <li>
            <a href="http://infolis.gesis.org/search?id=${$('.hiddenId').attr('value')}">
                <img style='height: 1em' src='http://infolis.github.io/img/logo-circle.png'/>
                Search InFoLiS!
            </a>
        </li>
        `);
    fulltextMappings.forEach(function(map) {
        document.querySelectorAll(map.selector).forEach(function(link) {
            link.innerHTML = '<img style="height: 1em" src="'+map.img+'"/> '+map.text;
        });
    });
});
