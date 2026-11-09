/* =========================================================
   ARAM MIZURI ARCHITECTURE — review.js
   A client's review page (/review/…): their voice, recorded right here
   (MediaRecorder, up to 90 seconds, with a live level meter) or chosen as a
   voice note; their photo, shrunk in the browser before it is sent (the
   pencil portrait is drawn from it in the admin); and the sending of it all.
   ========================================================= */
(function () {
    'use strict';

    const form = document.getElementById('rvForm');
    if (!form) return;
    const MAX_SECONDS = 90;
    const status = document.getElementById('rvStatus');
    const say = (text, ok) => { status.hidden = false; status.textContent = text; status.classList.toggle('is-err', ok === false); };

    /* ── The voice ──────────────────────────────────────────────────── */
    const recBtn = document.getElementById('rvRecBtn');
    const recLabel = recBtn.querySelector('.rv-rec__label');
    const recTime = document.getElementById('rvRecTime');
    const wave = document.getElementById('rvWave');
    const take = document.getElementById('rvTake');
    const audio = document.getElementById('rvAudio');
    const voiceFile = document.getElementById('rvVoiceFile');
    let voice = null;          // the Blob or File to send
    let recorder = null, stream = null, chunks = [], timer = null, started = 0, raf = null, ctx = null;

    // the format that plays most widely: AAC in MP4 where the browser can make
    // it (Safari, recent Chrome), else Opus in WebM
    const TYPES = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
    const canRecord = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    const recType = canRecord ? (TYPES.find(t => MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) || '') : '';
    if (!canRecord) document.getElementById('rvRec').hidden = true;

    const fmt = s => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
    const bars = Array.from({ length: 28 }, () => { const b = document.createElement('i'); wave.appendChild(b); return b; });

    function setVoice(blob) {
        voice = blob;
        if (audio.src) URL.revokeObjectURL(audio.src);
        audio.src = blob ? URL.createObjectURL(blob) : '';
        take.hidden = !blob;
        recLabel.textContent = blob ? form.dataset.again : form.dataset.rec;
    }

    function stopRecording() {
        if (recorder && recorder.state !== 'inactive') recorder.stop();
        clearInterval(timer); cancelAnimationFrame(raf);
        if (stream) stream.getTracks().forEach(t => t.stop());
        if (ctx) ctx.close().catch(() => {});
        recBtn.classList.remove('is-on');
        wave.hidden = true;
    }

    recBtn.addEventListener('click', async () => {
        if (recorder && recorder.state === 'recording') return stopRecording();
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
        } catch (e) {
            say(form.dataset.msgMic, false);
            document.getElementById('rvRec').hidden = true;
            return;
        }
        status.hidden = true;
        chunks = [];
        recorder = new MediaRecorder(stream, recType ? { mimeType: recType } : undefined);
        recorder.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };
        recorder.onstop = () => {
            const type = (recorder.mimeType || recType || 'audio/webm').split(';')[0];
            if (chunks.length) setVoice(new Blob(chunks, { type }));
        };
        recorder.start(250);
        started = Date.now();
        recBtn.classList.add('is-on');
        recLabel.textContent = form.dataset.stop;
        recTime.hidden = false; wave.hidden = false;
        timer = setInterval(() => {
            const s = (Date.now() - started) / 1000;
            recTime.textContent = fmt(s) + ' / ' + fmt(MAX_SECONDS);
            if (s >= MAX_SECONDS) stopRecording();
        }, 200);

        // a live level meter, so they can see they are being heard
        try {
            ctx = new (window.AudioContext || window.webkitAudioContext)();
            const an = ctx.createAnalyser(); an.fftSize = 64;
            ctx.createMediaStreamSource(stream).connect(an);
            const data = new Uint8Array(an.frequencyBinCount);
            const draw = () => {
                an.getByteFrequencyData(data);
                bars.forEach((b, i) => { b.style.transform = 'scaleY(' + Math.max(.08, data[i % data.length] / 255) + ')'; });
                raf = requestAnimationFrame(draw);
            };
            draw();
        } catch (e) { /* the meter is a nicety */ }
    });

    document.getElementById('rvTakeRemove').addEventListener('click', () => { setVoice(null); voiceFile.value = ''; recTime.hidden = true; });

    voiceFile.addEventListener('change', () => {
        const f = voiceFile.files[0];
        if (!f) return;
        if (f.size > +form.dataset.maxVoice) { say(form.dataset.msgBig, false); voiceFile.value = ''; return; }
        status.hidden = true;
        setVoice(f);
    });

    /* ── The photo ──────────────────────────────────────────────────── */
    const photoIn = document.getElementById('rvPhoto');
    const photoLabel = document.getElementById('rvPhotoLabel');
    const preview = document.getElementById('rvPhotoPreview');
    const photoRemove = document.getElementById('rvPhotoRemove');
    let photo = null;

    // shrunk to 1200px in the browser: quicker from a phone, and a full-size
    // phone photo is more than the drawing can open on the host
    function shrink(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const scale = Math.min(1, 1200 / Math.max(img.naturalWidth, img.naturalHeight));
                const c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * scale); c.height = Math.round(img.naturalHeight * scale);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(img.src);
                c.toBlob(b => b ? resolve(b) : reject(), 'image/jpeg', .9);
            };
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    }

    photoIn.addEventListener('change', async () => {
        const f = photoIn.files[0];
        if (!f) return;
        try { photo = await shrink(f); } catch (e) { photo = f; }
        preview.style.backgroundImage = 'url("' + URL.createObjectURL(photo) + '")';
        preview.hidden = false; photoRemove.hidden = false;
        photoLabel.textContent = f.name;
    });
    photoRemove.addEventListener('click', () => {
        photo = null; photoIn.value = ''; preview.hidden = true; photoRemove.hidden = true;
        photoLabel.textContent = photoLabel.dataset.empty;
    });

    /* ── Sending ────────────────────────────────────────────────────── */
    form.addEventListener('submit', e => {
        e.preventDefault();
        if (recorder && recorder.state === 'recording') stopRecording();
        if (!form.checkValidity()) { form.reportValidity(); say(form.dataset.msgCheck, false); return; }
        const body = new FormData(form);
        if (voice) {
            const ext = /mp4|m4a|aac/.test(voice.type) ? 'm4a' : (/ogg|opus/.test(voice.type) ? 'ogg' : (/mpeg|mp3/.test(voice.type) ? 'mp3' : (/wav/.test(voice.type) ? 'wav' : 'webm')));
            body.append('voice', voice, voice.name || ('voice.' + ext));
        }
        if (photo) body.append('photo', photo, 'photo.jpg');
        const btn = form.querySelector('.rv-send');
        btn.disabled = true;
        say(form.dataset.msgSending);
        fetch(form.action, { method: 'POST', body, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(() => {
                form.hidden = true;
                document.querySelector('.rv-head').hidden = true;
                const done = document.getElementById('rvDone');
                done.hidden = false;
                done.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch(r => {
                btn.disabled = false;
                const code = r && r.status;
                say(code === 410 ? form.dataset.msgClosed : (code === 422 ? form.dataset.msgCheck : form.dataset.msgFailed), false);
            });
    });
})();
