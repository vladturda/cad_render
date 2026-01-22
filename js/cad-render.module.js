import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/examples/jsm/loaders/DRACOLoader.js';

(function(Drupal, once) {
  Drupal.behaviors.cadRender = {
    attach(context, drupalSettings) {
      once('cad-render-block-init', '.cad-render-block-wrapper', context).forEach((element) => { 
        const uniqueId = element.dataset['uniqueId'];
        const config = drupalSettings['cadRender']['blocks'][uniqueId];
        initCadRender(element, config);
      });
    }
  };

  function initCadRender(element, config) {
    console.log(config);
    const container = element.querySelector('.cad-render-block-container');

    if (config.cad_render_file !== undefined) {
      const scene = new THREE.Scene();

      const dimensions = {
        width: config.width || container.clientWidth,
        height: config.height || container.clientHeight,
      };

      let camera;
      switch (config.camera_type ?? 'perspective') {
        case 'perspective':
          camera = new THREE.PerspectiveCamera(45, dimensions.width / dimensions.height, 0.1, 1000);
          break;
        case 'orthographic':
          camera = new THREE.OrthographicCamera( -dimensions.width / 2000, dimensions.width / 2000, dimensions.height / 2000, -dimensions.height / 2000, 0.1, 1000 );
          break;
      }

      camera.zoom = config.camera_zoom ?? 1;
      camera.updateProjectionMatrix();
      camera.position.set(2, 15, 2);

      const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
      renderer.setSize(config.width || container.clientWidth, config.height || container.clientHeight);
      renderer.setPixelRatio(window.devicePixelRatio); // sharpness
      container.appendChild(renderer.domElement);

      const pmremGenerator = new THREE.PMREMGenerator(renderer);
      pmremGenerator.compileEquirectangularShader();

      const env = new RoomEnvironment();
      const envMap = pmremGenerator.fromScene(env).texture;
      //scene.environment = envMap;
      //scene.background = envMap;

      // Set background color.
      if (config.background_color) {
        renderer.setClearColor(config.background_color, 1.0);
      }

      // Set scene lighting.
      const light = new THREE.DirectionalLight(0xffffff, 2.5);
      light.position.set(5, 5, 5);
      scene.add(light);
      scene.add(new THREE.AmbientLight(0xffffff, 1.0));

      // Load model.
      const loader = new GLTFLoader();
      const dracoLoader = new DRACOLoader();
      dracoLoader.setDecoderPath(new URL('../draco/', import.meta.url).href);
      loader.setDRACOLoader(dracoLoader);

      let model;
      let pivot;

      let animateActive = false;

      const glbPath = new URL(config.cad_render_file, import.meta.url).href;

      loader.load(glbPath, (glb) => {
        model = glb.scene;

        // Center model and setup pivot for rotation.
        const box = new THREE.Box3().setFromObject(model);
        const center = box.getCenter(new THREE.Vector3());
        model.position.sub(center);
        pivot = new THREE.Object3D();
        pivot.add(model);
        scene.add(pivot);

        // Move camera to fit model.
        const boxSize = box.getSize(new THREE.Vector3()).length();
        camera.position.set(boxSize * 0.7, boxSize * 0.5, boxSize * 0.7);
        camera.lookAt(0, 0, 0);

        // Keep reference to all meshes in model for later updates.
        const meshes = [];
        model.traverse((child) => {
          if (child.isMesh) meshes.push(child);
        });

        // Update mesh materials.
        for (const mesh of meshes) {
          mesh.geometry.computeVertexNormals();

          mesh.material = new THREE.MeshStandardMaterial({
            color: mesh.material.color.clone(),
            metalness: 0.2,
            roughness: 0.8,
            side: THREE.FrontSide,
            depthTest: true,
            depthWrite: config.transparency ? false : true,
            transparent: config.transparency ? true : false,
            opacity: config.opacity ?? 1.0,
          });

          if (config.material === 'solid_color') {
            mesh.material.color = new THREE.Color(config.solid_color || '#ffffff');
          }

          // Add wireframes if enabled.
          if (config.wireframe) {
            const edges = new THREE.EdgesGeometry(mesh.geometry, 30);
            const lines = new THREE.LineSegments(
              edges,
              new THREE.LineBasicMaterial({ 
                color: 0x555555,
                depthTest: false,
                depthWrite: false,
                transparent: true,
                opacity: 0.3,
              }),
            );
            lines.renderOrder = 999;
            mesh.add(lines);
          }
        }

        // Initial render.
        renderer.render(scene, camera);

        // Setup animation based on config.
        switch (config.animation ?? 'none') {
          case 'rotate':
            renderer.setAnimationLoop(animate);
            break;
          case 'rotate_on_hover':
            renderer.domElement.addEventListener( 'mouseover', toggleAnimation);
            renderer.domElement.addEventListener( 'mouseout', toggleAnimation);
            requestAnimationFrame(() => {
              if (renderer.domElement.matches(':hover')) {
                toggleAnimation();
              }
            });
            break;
        }
      }, undefined, (error) => { console.error(error); });
      
      function animate() {
        pivot.rotation.y += 0.005;
        renderer.render(scene, camera);
      }

      function toggleAnimation() {
        if (animateActive) {
          renderer.setAnimationLoop(null);
          animateActive = false;
        } else {
          renderer.setAnimationLoop(animate);
          animateActive = true;
        }
      }

      function onWindowResize() {
        const dimensions = {
          width: config.width || container.clientWidth,
          height: config.height || container.clientHeight,
        };

        switch (config.camera_type ?? 'perspective') {
          case 'perspective':
            camera.aspect = dimensions.width / dimensions.height;
            break;
          case 'orthographic':
            camera.left = -dimensions.width / 2000;
            camera.right = dimensions.width / 2000;
            camera.top = dimensions.height / 2000;
            camera.bottom = -dimensions.height / 2000;
            break;
        }
        camera.updateProjectionMatrix();

        renderer.setSize( dimensions.width, dimensions.height );
        renderer.render(scene, camera);
      }

      window.addEventListener ('resize', onWindowResize);
    }
  }
})(Drupal, once);
