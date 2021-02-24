$(document).on("ready", function () {
    generateCSRModal();
    const modalGenerateCsr = $('#modalGenerateCsr');
    const pageModalCover = $('#page-modal-cover');
    showCSRModal(pageModalCover, modalGenerateCsr);
    hideCSRModal(pageModalCover, modalGenerateCsr);
    submitCSRModal();
    goToStepTwo();

});

function submitCSRModal() {
    $('#submitModalBtn').on('click', function () {
        const modal =  $('#modalGenerateCsrForm')
        modal.trigger('submit');
    });
}

function showCSRModal(pageModalCover, modalGenerateCsr)
{
    $("#generateCsrBtn").off().on('click', function (e) {
        pageModalCover.css({"visibility" : "visible", "opacity": "1"});
        modalGenerateCsr.css({"visibility" : "visible", "top" : "10%px",  "opacity" : "1"});
        modalGenerateCsr.show();
    });
}

function hideCSRModal(pageModalCover, modalGenerateCsr)
{
    $('#closeCrossBtn').off().on('click', function () {
        modalGenerateCsr.find('input').val("");
        $('#action').val("generateCSR");
        pageModalCover.css({"visibility" : "hidden", "opacity": "0"});
        modalGenerateCsr.hide();
    });

    $('#closeModalBtn').off().on('click', function () {
        modalGenerateCsr.find('input').val("");
        $('#action').val("generateCSR");
        pageModalCover.css({"visibility" : "hidden", "opacity": "0"});
        modalGenerateCsr.hide();
    });
}

function goToStepTwo() {
    $("#continueBtn").on('click', function (e) {

        const url = '../clientGenerateCert/';
        let csr =  $('textarea[name=csr]').val();
        let form  = $('<form action="' + url + '" method="post">' +
            '<input type="text" name="csr_empty" value="false" />' +
            '</form>');

        if(csr.length <= 70)
        {
            form = $('<form action="' + url + '" method="post">' +
                '<input type="text" name="csr_empty" value="true" />' +
                '</form>');

            $('body').append(form);
            form.trigger('submit');
        }
        else
        {
            $('#certificateForm').trigger('submit');
        }
    });
}

function generateCSRModal() {
    $('form').attr("id", "certificateForm");

    const url = '../adminGenerateCert/';
    const csrfToken = $('input[name=_csrf_token]').val();
    const body = $('body');
    let countries = {"PL":"Poland","AF":"Afghanistan","AX":"Aland Islands","AL":"Albania","DZ":"Algeria","AS":"American Samoa","AD":"Andorra","AO":"Angola","AI":"Anguilla","AQ":"Antarctica","AG":"Antigua And Barbuda","AR":"Argentina","AM":"Armenia","AW":"Aruba","AU":"Australia","AT":"Austria","AZ":"Azerbaijan","BS":"Bahamas","BH":"Bahrain","BD":"Bangladesh","BB":"Barbados","BY":"Belarus","BE":"Belgium","BZ":"Belize","BJ":"Benin","BM":"Bermuda","BT":"Bhutan","BO":"Bolivia","BA":"Bosnia And Herzegovina","BW":"Botswana","BR":"Brazil","IO":"British Indian Ocean Territory","BN":"Brunei Darussalam","BG":"Bulgaria","BF":"Burkina Faso","BI":"Burundi","KH":"Cambodia","CM":"Cameroon","CA":"Canada","CV":"Cape Verde","KY":"Cayman Islands","CF":"Central African Republic","TD":"Chad","CL":"Chile","CN":"China","CX":"Christmas Island","CC":"Cocos (Keeling) Islands","CO":"Colombia","KM":"Comoros","CG":"Congo","CD":"Congo - Democratic Republic","CK":"Cook Islands","CR":"Costa Rica","CI":"Cote D'Ivoire","HR":"Croatia","CU":"Cuba","CW":"Curacao","CY":"Cyprus","CZ":"Czech Republic","DK":"Denmark","DJ":"Djibouti","DM":"Dominica","DO":"Dominican Republic","EC":"Ecuador","EG":"Egypt","SV":"El Salvador","GQ":"Equatorial Guinea","ER":"Eritrea","EE":"Estonia","ET":"Ethiopia","FK":"Falkland Islands (Malvinas)","FO":"Faroe Islands","FJ":"Fiji","FI":"Finland","FR":"France","GF":"French Guiana","PF":"French Polynesia","TF":"French Southern Territories","GA":"Gabon","GM":"Gambia","GE":"Georgia","DE":"Germany","GH":"Ghana","GI":"Gibraltar","GR":"Greece","GL":"Greenland","GD":"Grenada","GP":"Guadeloupe","GU":"Guam","GT":"Guatemala","GG":"Guernsey","GN":"Guinea","GW":"Guinea-Bissau","GY":"Guyana","HT":"Haiti","HM":"Heard Island & Mcdonald Islands","VA":"Holy See (Vatican City State)","HN":"Honduras","HK":"Hong Kong","HU":"Hungary","IS":"Iceland","IN":"India","ID":"Indonesia","IR":"Iran - Islamic Republic Of","IQ":"Iraq","IE":"Ireland","IM":"Isle Of Man","IL":"Israel","IT":"Italy","JM":"Jamaica","JP":"Japan","JE":"Jersey","JO":"Jordan","KZ":"Kazakhstan","KE":"Kenya","KI":"Kiribati","KR":"Korea","KW":"Kuwait","KG":"Kyrgyzstan","LA":"Lao People's Democratic Republic","LV":"Latvia","LB":"Lebanon","LS":"Lesotho","LR":"Liberia","LY":"Libyan Arab Jamahiriya","LI":"Liechtenstein","LT":"Lithuania","LU":"Luxembourg","MO":"Macao","MK":"Macedonia","MG":"Madagascar","MW":"Malawi","MY":"Malaysia","MV":"Maldives","ML":"Mali","MT":"Malta","MH":"Marshall Islands","MQ":"Martinique","MR":"Mauritania","MU":"Mauritius","YT":"Mayotte","MX":"Mexico","FM":"Micronesia - Federated States Of","MD":"Moldova","MC":"Monaco","MN":"Mongolia","ME":"Montenegro","MS":"Montserrat","MA":"Morocco","MZ":"Mozambique","MM":"Myanmar","NA":"Namibia","NR":"Nauru","NP":"Nepal","NL":"Netherlands","AN":"Netherlands Antilles","NC":"New Caledonia","NZ":"New Zealand","NI":"Nicaragua","NE":"Niger","NG":"Nigeria","NU":"Niue","NF":"Norfolk Island","MP":"Northern Mariana Islands","NO":"Norway","OM":"Oman","PK":"Pakistan","PW":"Palau","PS":"Palestine - State of","PA":"Panama","PG":"Papua New Guinea","PY":"Paraguay","PE":"Peru","PH":"Philippines","PN":"Pitcairn","PT":"Portugal","PR":"Puerto Rico","QA":"Qatar","RE":"Reunion","RO":"Romania","RU":"Russian Federation","RW":"Rwanda","BL":"Saint Barthelemy","SH":"Saint Helena","KN":"Saint Kitts And Nevis","LC":"Saint Lucia","MF":"Saint Martin","PM":"Saint Pierre And Miquelon","VC":"Saint Vincent And Grenadines","WS":"Samoa","SM":"San Marino","ST":"Sao Tome And Principe","SA":"Saudi Arabia","SN":"Senegal","RS":"Serbia","SC":"Seychelles","SL":"Sierra Leone","SG":"Singapore","SK":"Slovakia","SI":"Slovenia","SB":"Solomon Islands","SO":"Somalia","ZA":"South Africa","GS":"South Georgia And Sandwich Isl.","ES":"Spain","LK":"Sri Lanka","SD":"Sudan","SS":"South Sudan","SR":"Suriname","SJ":"Svalbard And Jan Mayen","SZ":"Swaziland","SE":"Sweden","CH":"Switzerland","SY":"Syrian Arab Republic","TW":"Taiwan","TJ":"Tajikistan","TZ":"Tanzania","TH":"Thailand","TL":"Timor-Leste","TG":"Togo","TK":"Tokelau","TO":"Tonga","TT":"Trinidad And Tobago","TN":"Tunisia","TR":"Turkey","TM":"Turkmenistan","TC":"Turks And Caicos Islands","TV":"Tuvalu","UG":"Uganda","UA":"Ukraine","AE":"United Arab Emirates","GB":"United Kingdom","US":"United States","UM":"United States Outlying Islands","UY":"Uruguay","UZ":"Uzbekistan","VU":"Vanuatu","VE":"Venezuela","VN":"Viet Nam","VG":"Virgin Islands - British","VI":"Virgin Islands - U.S.","WF":"Wallis And Futuna","EH":"Western Sahara","YE":"Yemen","ZM":"Zambia","ZW":"Zimbabwe"};
    let countryOptions = '';
    for (let key in countries) {
        countryOptions += '<option value="' + key + '">'+ countries[key] + '</option>'
    }
    body.append(
        '\
        <div id="page-modal-cover" style="visibility: hidden; opacity: 0">\n\
        <div class="open" id="modalGenerateCsr" style="visibility: hidden; top: 0px; opacity: 0">\n\
            <div class="modal-dialog">\n\
                <div class="modal-content panel panel-primary">\n\
                    <div class="modal-header panel-heading">\n\
                        <button type="button" class="close" id="closeCrossBtn" data-dismiss="modal">\n\
                            <span aria-hidden="true">&times;</span>\n\
                            <span class="sr-only">Close</span>\n\
                        </button>\n\
                        <h4 class="modal-title">'+'Generate CSR'+'</h4>\n\
                    </div>\n\
                    <form action="' + url + '" id="modalGenerateCsrForm" method="post">\n\
                    <div class="modal-body panel-body" id="modalgenerateCsrBody">\n\
                        <div class="alert alert-danger hidden" id="modalgenerateCsrDanger">\n\
                            <strong>Error!</strong> <span></span>\n\
                        </div>\n\
                        <form>\n\
                            <div class="col-md-1"></div>\n\
                            <div class="col-md-10" style="width:80%;">\n\
                                   <div class="form-group" hidden>\n\
                                    <label class="control-label" for="csrfToken"></label>\n\
                                    <input class="form-control  generateCsrInput" id="csrfToken" name="_csrf_token" value="'+ csrfToken +'">\n\
                                  </div>\n\
                                   <div class="form-group" hidden>\n\
                                    <label class="control-label" for="action"></label>\n\
                                    <input class="form-control  generateCsrInput" id="action" name="action" value="generateCSR">\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="C">'+'Country'+'</label>\n\
                                    <select class="form-control  generateCsrInput" id="countryName" name="C" required="required">\n\
                                     ' + countryOptions + '\n\
                                    </select>\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="ST">'+'State'+'</label>\n\
                                    <input class="form-control generateCsrInput"  id="stateOrProvinceName" placeholder="'+'Texas'+'" name="ST" required="required" type="text">\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="L">'+'Locality'+'</label>\n\
                                    <input class="form-control generateCsrInput" id="localityName" placeholder="'+'San Antonio'+'" name="L" required="required" type="text">\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="O">'+'Organization'+'</label>\n\
                                    <input class="form-control generateCsrInput" id="organizationName" placeholder="'+'company name'+'" name="O" required="required" type="text">\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="OU">'+'Organization Unit'+'</label>\n\
                                    <input class="form-control generateCsrInput" id="organizationalUnitName" placeholder="'+'Marketing'+'" name="OU" required="required" type="text">\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="CN">'+'Common Name'+'</label>\n\
                                    <input class="form-control generateCsrInput" autocomplete="off" id="commonName" placeholder="'+'example.com'+'" name="CN" required="required" type="text">\n\
                                  </div>\n\
                                  <div class="form-group">\n\
                                    <label class="control-label" for="EA">'+'Email Address'+'</label>\n\
                                    <input class="form-control generateCsrInput" id="emailAddress" placeholder="'+'example@example.com'+'" name="EA" required="required" type="text">\n\
                                  </div>\n\
                              </div>\n\
                            <div class="col-md-1"></div>\n\
                    </div>\n\
                    <div class="modal-footer panel-footer">\n\
                        <button type="button" id="submitModalBtn" class="btn btn-primary">\n\
                            '+'Submit'+'\n\
                        </button>\n\
                        <button type="button" class="btn btn-default" id="closeModalBtn" data-dismiss="modal">\n\
                            '+'Close'+'\n\
                        </button>\n\
                    </div>\n\
                    </form>\n\
                </div>\n\
            </div>\
       </div>\
       </div>'
    );
}