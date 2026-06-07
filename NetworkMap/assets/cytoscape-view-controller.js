/**
 * Provides reusable interaction helpers for a Cytoscape graph.
 *
 * Domain-specific styles, data interpretation and layout definitions remain
 * the responsibility of the consuming visualization.
 */
class CytoscapeViewController {
    constructor(cy, options = {}) {
        this.cy = cy;
        this.layouts = options.layouts || {};
        this.searchFields = options.searchFields || ['id', 'label'];
        this.selectedNode = null;
    }

    runLayout(name) {
        const definition = this.layouts[name];
        if (!definition) {
            return false;
        }

        const options = typeof definition === 'function' ? definition(this.cy) : definition;
        if (!options || typeof options !== 'object') {
            return false;
        }

        this.cy.elements(':visible').layout({ animate: false, fit: true, ...options }).run();
        return true;
    }

    setLabelsVisible(visible) {
        this.cy.nodes().style('label', visible ? 'data(label)' : '');
    }

    findNode(query) {
        const needle = String(query || '').trim().toLocaleLowerCase();
        if (!needle) {
            return null;
        }

        const nodes = this.cy.nodes();
        const exactMatch = nodes.filter(node => this.searchFields.some(field =>
            String(node.data(field) ?? '').toLocaleLowerCase() === needle
        ));
        if (exactMatch.length > 0) {
            return exactMatch[0];
        }

        const partialMatch = nodes.filter(node => this.searchFields.some(field =>
            String(node.data(field) ?? '').toLocaleLowerCase().includes(needle)
        ));
        return partialMatch.length > 0 ? partialMatch[0] : null;
    }

    selectNode(node) {
        const resolvedNode = this.resolveNode(node);
        if (!resolvedNode) {
            return false;
        }

        this.cy.nodes().removeClass('selected');
        resolvedNode.addClass('selected');
        this.selectedNode = resolvedNode;
        return true;
    }

    focusNode(node, includeNeighborhood = false) {
        const resolvedNode = this.resolveNode(node);
        if (!resolvedNode) {
            return false;
        }

        this.clearFocus(false);
        this.selectNode(resolvedNode);
        const focusElements = includeNeighborhood ? resolvedNode.closedNeighborhood() : resolvedNode;
        this.cy.elements().difference(focusElements).addClass('faded');
        focusElements.addClass('focused-neighborhood');
        this.cy.animate({ fit: { eles: focusElements, padding: 60 }, duration: 200 });
        return true;
    }

    focusSelectedNeighborhood() {
        return this.selectedNode ? this.focusNode(this.selectedNode, true) : false;
    }

    clearFocus(fit = true) {
        this.cy.elements().removeClass('faded focused-neighborhood selected');
        this.selectedNode = null;
        if (fit) {
            this.cy.fit(this.cy.elements(':visible'), 40);
        }
    }

    resolveNode(node) {
        if (typeof node === 'string') {
            const resolved = this.cy.getElementById(node);
            return resolved.length > 0 ? resolved : null;
        }
        return node && typeof node.isNode === 'function' && node.isNode() ? node : null;
    }
}

globalThis.CytoscapeViewController = CytoscapeViewController;
