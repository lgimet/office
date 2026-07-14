export class DragManager {
  constructor(options) {
    this.sourceSelector = options.sourceSelector;
    this.targetSelector = options.targetSelector;
    this.onDrop = options.onDrop;

    this.dragged = null;
    this.ghost = null;

    this.init();
  }

  init() {
    document.querySelectorAll(this.sourceSelector).forEach(el => {
      el.addEventListener('mousedown', this.startDrag.bind(this));
    });
  }

  startDrag(e) {
    this.dragged = e.currentTarget;

    this.createGhost(e);

    document.addEventListener('mousemove', this.onMove);
    document.addEventListener('mouseup', this.onEnd);
  }

  createGhost(e) {
    this.ghost = this.dragged.cloneNode(true);
    this.ghost.classList.add('drag-ghost');

    document.body.appendChild(this.ghost);
    this.moveGhost(e.pageX, e.pageY);
  }

  moveGhost(x, y) {
    this.ghost.style.left = x + 'px';
    this.ghost.style.top = y + 'px';
  }

  onMove = (e) => {
    this.moveGhost(e.pageX, e.pageY);

    // highlight
    document.querySelectorAll(this.targetSelector).forEach(el => {
      el.classList.remove('drag-over');
    });

    const target = document.elementFromPoint(e.clientX, e.clientY);
    const dropZone = target && target.closest(this.targetSelector);

    if (dropZone) {
      dropZone.classList.add('drag-over');
    }
  }

  onEnd = (e) => {

    document.removeEventListener('mousemove', this.onMove);
    document.removeEventListener('mouseup', this.onEnd);

    const target = document.elementFromPoint(e.clientX, e.clientY);
    const dropZone = target && target.closest(this.targetSelector);

    if (dropZone && this.onDrop) {
      this.onDrop({
        dragged: this.dragged,
        dropZone: dropZone
      });
    }

    this.cleanup();
  }

  cleanup() {
    if (this.ghost) {
      this.ghost.remove();
    }

    this.ghost = null;
    this.dragged = null;

    document.querySelectorAll(this.targetSelector).forEach(el => {
      el.classList.remove('drag-over');
    });
  }
}
