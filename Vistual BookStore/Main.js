let currentIndex = 0;

function slide(direction) {
    const slider = document.querySelector('.wrapper-track');
    const items = document.querySelectorAll('.item');
    const itemWidth = items[0].offsetWidth;
    const containerWidth = document.querySelector('.wrapper').offsetWidth;
    const maxIndex = Math.ceil((items.length * itemWidth) / containerWidth) - 1;

    currentIndex += direction;

    if (currentIndex < 0) {
        currentIndex = 0;
    } else if (currentIndex > maxIndex) {
        currentIndex = maxIndex;
    }

    const offset = -currentIndex * containerWidth;
    slider.style.transform = `translateX(${offset}px)`;
}