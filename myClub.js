const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', function () {
    if (window.scrollY > 50) {
        navbar.style.padding = "10px 8%";
        navbar.style.background = "rgba(5, 8, 16, 0.95)";
        navbar.style.boxShadow = "0 5px 20px rgba(0, 243, 255, 0.3)";
    } else {
        navbar.style.padding = "20px 8%";
        navbar.style.background = "rgba(5, 8, 16, 0.85)";
        navbar.style.boxShadow = "0 4px 20px rgba(0, 0, 0, 0.5)";
    }
});


const sections = document.querySelectorAll('section, header.home');
const navLinks = document.querySelectorAll('.navbar ul li');

window.addEventListener('scroll', () => {
    let current = '';

    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (pageYOffset >= (sectionTop - sectionHeight / 3)) {
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(li => {
        li.classList.remove('active');
        const link = li.querySelector('a');
        if (link && current && link.getAttribute('href') === `#${current}`) {
            li.classList.add('active');
        }
    });
});
