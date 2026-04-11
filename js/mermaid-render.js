(function (Drupal, once) {
  'use strict';

  /**
   * Renders Mermaid diagrams from <pre data-mermaid-source> elements.
   *
   * The <pre> contains raw Mermaid code as visible fallback.
   * This behavior initializes Mermaid with strict security, renders
   * the diagram, and replaces the <pre> with the SVG.
   *
   * Uses DOMParser + replaceChildren() instead of innerHTML for XSS safety.
   */
  Drupal.behaviors.stateMachineUiMermaid = {
    attach: function (context) {
      var elements = once('mermaid-render', '[data-mermaid-source]', context);
      if (!elements.length || typeof mermaid === 'undefined') {
        return;
      }

      mermaid.initialize({
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'strict',
        stateDiagram: {
          defaultRenderer: 'dagre-wrapper'
        }
      });

      elements.forEach(function (preElement) {
        var code = preElement.textContent;
        if (!code || !code.trim()) {
          return;
        }

        var id = 'mermaid-' + Math.random().toString(36).substring(2, 10);

        mermaid.render(id, code.trim()).then(function (result) {
          // Parse SVG string safely via DOMParser.
          var parser = new DOMParser();
          var doc = parser.parseFromString(result.svg, 'image/svg+xml');
          var svgElement = doc.documentElement;

          // Create a container and replace the <pre> content.
          var container = document.createElement('div');
          container.className = 'state-machine-mermaid-rendered';
          container.replaceChildren(svgElement);

          // Replace the <pre> with the rendered container.
          preElement.replaceWith(container);
        }).catch(function (err) {
          // On error, keep the <pre> visible as fallback.
          preElement.classList.add('state-machine-mermaid-error');
        });
      });
    }
  };

})(Drupal, once);
