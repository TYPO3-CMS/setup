/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import{MessageUtility}from"@typo3/backend/utility/message-utility.js";import RegularEvent from"@typo3/core/event/regular-event.js";class SetupModule{constructor(){new RegularEvent("setup:confirmation:response",SetupModule.handleConfirmationResponse).delegateTo(document,'[data-event-name="setup:confirmation:response"]'),new RegularEvent("click",((e,t)=>{const a=new CustomEvent(t.dataset.eventName,{bubbles:!0,detail:{payload:t.dataset.eventPayload}});t.dispatchEvent(a)})).delegateTo(document,'[data-event="click"][data-event-name]'),document.querySelectorAll("[data-setup-avatar-field]").forEach((e=>{const t=e.dataset.setupAvatarField,a=document.getElementById("clear_button_"+t),n=document.getElementById("add_button_"+t);n.addEventListener("click",(()=>this.avatarOpenFileBrowser(n.dataset.setupAvatarUrl))),a&&a.addEventListener("click",(()=>this.avatarClearExistingImage(t)))})),null!==document.querySelector("[data-setup-avatar-field]")&&this.initializeMessageListener()}static handleConfirmationResponse(e){if(e.detail.result&&"resetConfiguration"===e.detail.payload){const e=document.querySelector("#setValuesToDefault");e.value="1",e.form.submit()}}static hideElement(e){e.style.display="none"}initializeMessageListener(){window.addEventListener("message",(e=>{if(!MessageUtility.verifyOrigin(e.origin))throw new Error("Denied message sent by "+e.origin);if("typo3:foreignRelation:insert"===e.data.actionName){if(void 0===e.data.objectGroup)throw new Error("No object group defined for message");const t=e.data.objectGroup.match(/avatar-(.+)$/);if(null===t)return;this.avatarSetFileUid(t[1],e.data.uid)}}))}avatarOpenFileBrowser(e){this.avatarWindowRef=window.open(e,"Typo3WinBrowser","height=650,width=800,status=0,menubar=0,resizable=1,scrollbars=1"),this.avatarWindowRef.focus()}avatarClearExistingImage(e){const t=document.getElementById("field_"+e),a=document.getElementById("image_"+e),n=document.getElementById("clear_button_"+e);n&&SetupModule.hideElement(n),a&&SetupModule.hideElement(a),t.value="delete"}avatarSetFileUid(e,t){this.avatarClearExistingImage(e);const a=document.getElementById("field_"+e),n=document.getElementById("add_button_"+e);a.value=t,n.classList.remove("btn-default"),n.classList.add("btn-info"),this.avatarWindowRef instanceof Window&&!this.avatarWindowRef.closed&&(this.avatarWindowRef.close(),this.avatarWindowRef=null)}}export default new SetupModule;