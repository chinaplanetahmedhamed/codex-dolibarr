const $ = (id) => document.getElementById(id);
const state = { lines: [] };
const i18n = {
  ar: {dir:'rtl',titles:{quotation:'عرض سعر',proforma:'فاتورة مبدئية',commercial:'فاتورة تجارية',packing:'قائمة تعبئة',contract:'عقد بيع'},docNo:'رقم المستند',date:'التاريخ',seller:'البائع',buyer:'المشتري',currency:'العملة',loading:'ميناء التحميل',destination:'ميناء الوصول',image:'الصورة',description:'الوصف',qty:'الكمية',unitPrice:'سعر الوحدة',amount:'الإجمالي',subtotal:'المجموع الفرعي',grand:'الإجمالي النهائي',payment:'شروط الدفع',notes:'ملاحظات',prepared:'إعداد'},
  en: {dir:'ltr',titles:{quotation:'QUOTATION',proforma:'PROFORMA INVOICE',commercial:'COMMERCIAL INVOICE',packing:'PACKING LIST',contract:'SALES CONTRACT'},docNo:'Document No.',date:'Date',seller:'Seller',buyer:'Buyer',currency:'Currency',loading:'Loading Port',destination:'Destination Port',image:'Image',description:'Description',qty:'Qty',unitPrice:'Unit Price',amount:'Amount',subtotal:'Subtotal',grand:'Grand Total',payment:'Payment Terms',notes:'Notes',prepared:'Prepared by'},
  zh: {dir:'ltr',titles:{quotation:'报价单',proforma:'形式发票',commercial:'商业发票',packing:'装箱单',contract:'销售合同'},docNo:'单据编号',date:'日期',seller:'卖方',buyer:'买方',currency:'币种',loading:'装货港',destination:'目的港',image:'图片',description:'产品描述',qty:'数量',unitPrice:'单价',amount:'金额',subtotal:'小计',grand:'总金额',payment:'付款条款',notes:'备注',prepared:'制单'}
};

function money(v){const cur=$('currency').value;return `${Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})} ${cur}`}
function addLine(data={description:'',qty:1,price:0,image:''}){
  const id=crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
  state.lines.push({...data,id});
  const node=$('lineTemplate').content.cloneNode(true);
  const card=node.querySelector('.line-card');card.dataset.id=id;
  const desc=node.querySelector('.line-description'),qty=node.querySelector('.line-qty'),price=node.querySelector('.line-price'),image=node.querySelector('.line-image');
  desc.value=data.description;qty.value=data.qty;price.value=data.price;
  const update=()=>{const line=state.lines.find(x=>x.id===id);line.description=desc.value;line.qty=Number(qty.value||0);line.price=Number(price.value||0);render()};
  [desc,qty,price].forEach(el=>el.addEventListener('input',update));
  image.addEventListener('change',()=>{const file=image.files[0];if(!file)return;const reader=new FileReader();reader.onload=()=>{state.lines.find(x=>x.id===id).image=reader.result;render()};reader.readAsDataURL(file)});
  node.querySelector('.remove-line').addEventListener('click',()=>{state.lines=state.lines.filter(x=>x.id!==id);card.remove();render()});
  $('lineEditor').appendChild(node);render();
}
function setText(id,text){$(id).textContent=text}
function render(){
  const lang=$('languageSelect').value,t=i18n[lang],type=$('documentType').value;
  document.documentElement.lang=lang;document.documentElement.dir=t.dir;$('documentPreview').dir=t.dir;
  setText('previewTitle',t.titles[type]);setText('labelDocNo',t.docNo);setText('labelDate',t.date);setText('labelSeller',t.seller);setText('labelBuyer',t.buyer);setText('labelCurrency',t.currency);setText('labelLoadingPort',t.loading);setText('labelDestinationPort',t.destination);setText('thImage',t.image);setText('thDescription',t.description);setText('thQty',t.qty);setText('thUnitPrice',t.unitPrice);setText('thAmount',t.amount);setText('labelSubtotal',t.subtotal);setText('labelGrandTotal',t.grand);setText('labelPaymentTerms',t.payment);setText('labelNotes',t.notes);setText('labelPreparedBy',t.prepared);
  setText('previewDocNumber',$('docNumber').value);setText('previewDate',$('issueDate').value);setText('previewSeller',$('sellerName').value);setText('previewSellerAddress',$('sellerAddress').value);setText('previewBuyer',$('buyerName').value);setText('previewBuyerAddress',$('buyerAddress').value);setText('previewCurrency',$('currency').value);setText('previewIncoterm',$('incoterm').value);setText('previewLoadingPort',$('loadingPort').value);setText('previewDestinationPort',$('destinationPort').value);setText('previewPaymentTerms',$('paymentTerms').value);setText('previewNotes',$('notes').value);
  const tbody=$('previewLines');tbody.innerHTML='';let subtotal=0;
  state.lines.forEach((line,i)=>{const amount=line.qty*line.price;subtotal+=amount;const tr=document.createElement('tr');tr.innerHTML=`<td>${i+1}</td><td>${line.image?`<img class="product-thumb" src="${line.image}" alt="">`:'<div class="image-placeholder">NO IMAGE</div>'}</td><td>${escapeHtml(line.description)}</td><td class="num">${line.qty.toLocaleString()}</td><td class="num">${money(line.price)}</td><td class="num"><strong>${money(amount)}</strong></td>`;tbody.appendChild(tr)});
  setText('subtotalValue',money(subtotal));setText('grandTotalValue',money(subtotal));
}
function escapeHtml(value){return String(value||'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
function loadDemo(){
  $('docNumber').value='CP-QT-2026-0088';$('buyerName').value='Alexandria Modern Trading Co.';$('buyerAddress').value='Nasr City, Cairo, Egypt';$('currency').value='USD';$('incoterm').value='CIF';$('loadingPort').value='Ningbo, China';$('destinationPort').value='Alexandria, Egypt';$('paymentTerms').value='30% T/T deposit; 70% balance before shipment.';$('notes').value='Delivery: 25–30 days after deposit and artwork confirmation.';
  state.lines=[];$('lineEditor').innerHTML='';addLine({description:'Smart Electronic Door Lock — fingerprint, password, card and mobile application',qty:100,price:46.5});addLine({description:'Wireless Game Controller — Bluetooth, rechargeable battery',qty:200,price:18.75});render();
}
['languageSelect','documentType','docNumber','issueDate','currency','incoterm','sellerName','sellerAddress','buyerName','buyerAddress','loadingPort','destinationPort','paymentTerms','notes'].forEach(id=>$(id).addEventListener('input',render));
$('addLine').addEventListener('click',()=>addLine());$('loadDemo').addEventListener('click',loadDemo);$('printPdf').addEventListener('click',()=>window.print());
$('issueDate').value=new Date().toISOString().slice(0,10);loadDemo();
