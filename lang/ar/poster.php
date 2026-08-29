<?php

/*
 * The prompt handed to an image generator, in Arabic.
 *
 * Kept in Arabic because an owner writing in Arabic should be able to read,
 * edit and trust the prompt they are about to paste. The instruction not to
 * render lettering matters twice as much here: image models produce
 * disconnected Arabic letterforms that read as nonsense to anyone literate.
 */
return [
    'lead' => 'خلفية ملصق دعائي :mood لـ:kind. بجودة تحريرية تصلح للطباعة ولوسائل التواصل.',
    'about' => 'الفعالية باسم «:title»، تُقام في :venue يوم :when. لتوجّه هذه التفاصيل موضوع العمل وأجواءه، دون كتابة أيٍّ منها على الصورة.',
    'palette' => 'استخدم هذه الألوان تحديدًا ولا شيء خارجها: :colors.',
    'composition' => 'التكوين: :ratio، مع تركيز الثقل البصري في الثلثين العلويين.',
    'no_text' => 'مهم: بلا أي حروف أو كلمات أو أرقام أو لافتات أو علامات مائية في الصورة. سيُضاف كل النص لاحقًا.',
    'reserve_qr' => 'مهم: اترك الثلث السفلي هادئًا وخاليًا — لون مسطّح أو تدرّج ناعم، بلا تفاصيل ولا وجوه — ليُوضع فيه عنوان الفعالية ورمز الاستجابة بوضوح.',
    'negative' => 'نص، حروف، كلمات، أرقام، تعليقات، علامة مائية، توقيع، شعار، تفاصيل مزدحمة في الثلث السفلي، وجوه مشوّهة، أطراف زائدة',

    'kind' => [
        'concert' => 'حفل موسيقي حيّ',
        'theatre' => 'عرض مسرحي',
        'film' => 'عرض سينمائي',
        'lecture' => 'محاضرة أو ندوة',
        'exhibition' => 'معرض فنّي',
        'children' => 'عرض للأطفال',
        'festival' => 'مهرجان أهلي',
        'private' => 'احتفال خاص',
    ],

    'mood' => [
        'elegant' => 'أنيق ومتّزن',
        'bold' => 'جريء وعالي التباين',
        'minimal' => 'بسيط بمساحات فارغة سخية',
        'heritage' => 'متجذّر في التراث البصري السوري والشامي، بحجر البازلت والزخرفة التقليدية',
        'nocturne' => 'ليلي بأجواء مضاءة من مصدر واحد',
        'warm' => 'دافئ وجاذب بضوء الساعة الذهبية',
    ],
];
