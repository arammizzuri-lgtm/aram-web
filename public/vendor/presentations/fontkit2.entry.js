// fontkit 2 (maintained) behind the small surface pdf-lib 1.17 expects from @pdf-lib/fontkit:
// create(bytes) and, on a subset, encodeStream() — which fontkit 2 replaced with encode().
import { create } from 'fontkit';

export default {
    create(data) {
        const font = create(data instanceof Uint8Array ? data : new Uint8Array(data));
        const createSubset = font.createSubset.bind(font);
        font.createSubset = () => {
            const subset = createSubset();
            subset.encodeStream = () => {
                const on = {};
                const stream = {
                    on(event, fn) {
                        on[event] = fn;
                        if (on.data && on.end) queueMicrotask(() => { on.data(subset.encode()); on.end(); });
                        return stream;
                    },
                };
                return stream;
            };
            return subset;
        };
        return font;
    },
};
